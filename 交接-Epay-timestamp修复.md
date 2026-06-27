# 交接：分销系统对接 Epay 当面付 — 补 timestamp 修复（给 Codex）

## 背景（已完成的部分，别重做）

分销系统（Laravel，`/opt/sub2rebate/backend`，本地 `D:\test项目\分销\backend`）通过**易支付 MD5 协议**对接 Epay 当面付。
集成方式是**复用现有的通用易支付客户端** `app/Modules/Payment/Services/AliMPayService.php`，
通过把 `config_items` 表里 `payment.alimpay.*` 配置指向 Epay 网关实现（gateway=`https://pay.sjiaa.cc.cd`, pid=`1001`, key=`206492ff706711f1a10bae87b3df050d`）。
`payment.mode=alimpay_qr`，下单走 `AliMPayService::payUrl()` 生成 `submit.php?...` 跳转链接。

**下单已能跳转到 Epay**，但 Epay 报错 **"timestamp 不能为空"**，付不了。这就是要修的。

## 根因（已定位，确凿）

生产 Epay 升级后，`includes/lib/ApiHelper.php::api_verify()` 对**所有**商户 API 下单强制校验：

```php
if(empty($queryArr['timestamp'])) throw new Exception('timestamp 不能为空');
if(abs(time() - $queryArr['timestamp']) > 300) throw new Exception('时间戳字段不正确');
// nonce 可选，但若传则 >=8 位且不可重复
// 最后 verifySign(全部参数, key) —— 所以 timestamp 必须参与签名
```

而 `AliMPayService::payUrl()`（和 `sign()`）**没有传 `timestamp`**，所以被拒。
（旧版 AliMPay 网关 amt.sjiaa.cc.cd 不要求 timestamp，Epay 要求——这是两个网关的差异。）

## 改动点（只改 1 个文件）

文件：`app/Modules/Payment/Services/AliMPayService.php`

### 改 `payUrl()` 方法：参数里加 `timestamp`（在签名之前加，让它参与签名）

现状：
```php
public function payUrl(RechargeOrder $order): string
{
    $config = $this->config();
    $params = [
        'pid' => $config['pid'],
        'type' => 'alipay',
        'out_trade_no' => (string) $order->out_trade_no,
        'notify_url' => $config['notifyUrl'],
        'return_url' => $config['returnUrl'],
        'name' => (string) $order->subject,
        'money' => $this->money2($order->amount),
        'sitename' => $config['sitename'],
    ];
    $params['sign'] = $this->sign($params, $config['key']);
    $params['sign_type'] = 'MD5';
    return $config['gatewayUrl'].'/submit.php?'.http_build_query($params);
}
```

改成（**加一行 `'timestamp' => time(),`**，放在 `sign` 之前）：
```php
    $params = [
        'pid' => $config['pid'],
        'type' => 'alipay',
        'out_trade_no' => (string) $order->out_trade_no,
        'notify_url' => $config['notifyUrl'],
        'return_url' => $config['returnUrl'],
        'name' => (string) $order->subject,
        'money' => $this->money2($order->amount),
        'sitename' => $config['sitename'],
        'timestamp' => (string) time(),   // ← 新增：Epay 要求，且需参与签名
    ];
    $params['sign'] = $this->sign($params, $config['key']);
    $params['sign_type'] = 'MD5';
```

> `sign()` 方法不用改：它已经 `ksort + 去空 + k=v&拼接 + md5(str.key)`，timestamp 会自动参与签名。
> `verify()`（回调验签）也不用改：回调用的是 Epay 推过来的参数，timestamp 由 Epay 决定，验签逻辑通用。

## 注意事项

1. **时间同步**：Epay 校验 `abs(time()-timestamp) > 300` 拒绝。分销服务器和 Epay 都在 154.44.9.60 同机，时间一致，没问题。若将来分服务器，确保 NTP 同步。
2. **不要动 `verify()` / `sign()` 的算法**——它们是通用易支付 MD5，AliMPay 旧通道和回调还在用。
3. **不要改回调路由/入账逻辑**（`RechargeCallbackService`、`PaymentNotifyController`）——回调侧 Epay 推什么参数就验什么，已兼容。

## 验证步骤

1. 改完清缓存：
   ```bash
   cd /opt/sub2rebate/backend && php artisan config:clear && php artisan cache:clear
   ```
2. 用 bootstrap 脚本验证 payUrl 含 timestamp（生产无 tinker，用独立 php 脚本）：
   ```php
   // /tmp/check.php
   require '/opt/sub2rebate/backend/vendor/autoload.php';
   $app = require '/opt/sub2rebate/backend/bootstrap/app.php';
   $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   $o = new App\Modules\Payment\Models\RechargeOrder();
   $o->out_trade_no='T'.time(); $o->subject='测试'; $o->amount='1.00';
   echo app(App\Modules\Payment\Services\AliMPayService::class)->payUrl($o), PHP_EOL;
   ```
   `php /tmp/check.php` → URL 里应有 `&timestamp=...&...&sign=...`
3. **真机刷一笔**：充值页下单 → 跳 Epay 当面付收银台（不再报 timestamp 错）→ 扫码付款 → 回调入账 → Sub2API 余额增加。
4. 盯日志：`tail -f /opt/sub2rebate/backend/storage/logs/laravel.log`，看回调验签/入账。

## 单测（本地 D:\test项目\分销\backend）

`AliMPayRechargeTest.php` 已存在。改完跑：
```bash
php vendor/bin/phpunit --filter AliMPay
```
若测试断言了 payUrl 的参数，需同步把 timestamp 纳入预期（或断言 `str_contains($url,'timestamp=')`）。
跑全套确认无回归：`php vendor/bin/phpunit`（基线 167 tests 全绿）。

## 部署到生产（非 git，手动）

生产 `/opt/sub2rebate` 不是 git 仓库。改完只需把这 1 个文件传上去覆盖：
```bash
scp backend/app/Modules/Payment/Services/AliMPayService.php root@154.44.9.60:/opt/sub2rebate/backend/app/Modules/Payment/Services/AliMPayService.php
ssh root@154.44.9.60 'cd /opt/sub2rebate/backend && php artisan config:clear'
```
（改前先备份：`cp AliMPayService.php AliMPayService.php.bak`）

## 关键事实速查（环境）

- 分销后端：`/opt/sub2rebate/backend`，**SQLite** `/opt/sub2rebate/data/database.sqlite`，**生产无 tinker**
- Epay：`pay.sjiaa.cc.cd`（同机 docker，`/opt/epay`），商户 pid=1001，域名白名单已加 `rebate.sjiaa.cc.cd`
- 回调端点：`https://rebate.sjiaa.cc.cd/api/v1/payments/alimpay/notify`
- 原 AliMPay 配置备份：服务器 `/root/alimpay-config.bak`
