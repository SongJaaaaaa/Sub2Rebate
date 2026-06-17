# Sub2API Integration 模块：与 Sub2API 集成

## 1. 模块目标

负责 Sub2Rebate 与现有 Sub2API 的数据和业务衔接。

完整调研报告见 `docs/SUB2API_INTEGRATION_RESEARCH.md`。

## 2. 模块边界

本模块负责：

- 读取 Sub2API 用户。
- 读取 Sub2API 原生 `user_affiliates.aff_code`，生成用户可见的 Sub2API 邀请链接。
- 适配 Sub2API 用户登录校验。
- 扫描 Sub2API 充值、兑换、支付审计记录。
- 将外部充值事件转换为 `rebate_events`。
- 调用 Sub2API 管理接口查询上游账号和上游用量，支撑第一版运营监控。
- 记录扫描游标和幂等键。

本模块不负责：

- 返利金额计算。
- 提现。
- 前端页面。
- 直接改 Sub2API 核心源码。
- 直接复用 Sub2API 原生 affiliate 作为本系统分销树。

## 3. 当前结论

接口和源码层面可行：

- Sub2API 公网入口可访问。
- `admin/users` 才是用户账号体系，线上已确认返回用户总数和用户字段结构。
- Admin API 存在上游账号列表、上游账号详情、上游账号用量、上游账号统计接口。
- 上游账号监控第一版必须做，但只作为运营看板，不参与返利发放。
- 用户登录接口存在，账号密码字段为 `email` / `password`。
- `users.password_hash` 使用 bcrypt，可由 Laravel bcrypt verifier 适配。
- 线上库确认存在 `users`、`auth_identities`、`redeem_codes`、`payment_orders`、`payment_audit_logs`。
- 线上 `auth_identities` 当前只有 `email` provider，第一版按邮箱密码共用用户体系可行。
- Laravel 后端骨架已预留 `sub2api` 数据库连接。

生产环境仍待确认：

- 管理员暂时允许继续直接在 Sub2API 改 `users.balance`。
- 这类直接余额改动只能做人工确认和对账提示，不能自动发放返利。

## 4. 推荐方式

第一优先级：

```text
共享 PostgreSQL 只读连接
读取 users / auth_identities / user_affiliates / payment_orders / redeem_codes / payment_audit_logs
按 source_type + source_id 写入 rebate_events
队列触发返利引擎
```

第二优先级：

```text
Admin API
使用 x-api-key
读取上游账号列表、上游账号详情、上游账号用量、上游账号统计
支撑 B12 上游账号监控
```

兜底方式：

```text
users.balance / users.total_recharged 快照
仅用于辅助核对或人工确认
不作为第一版稳定返利事件源
```

不推荐：

```text
直接改 Sub2API 核心源码作为第一版方案
只监控 users.balance 差值触发返利
把 Sub2API 原生单级 affiliate 当作无限级返利系统
```

## 5. 已确认接口

登录和当前用户：

```http
POST /api/v1/auth/login
GET /api/v1/auth/me
GET /api/v1/user/profile
```

管理用户：

```http
GET /api/v1/admin/users
GET /api/v1/admin/users/{id}
POST /api/v1/admin/users/{id}/balance
GET /api/v1/admin/users/{id}/balance-history
```

上游账号和用量：

```http
GET /api/v1/admin/accounts
GET /api/v1/admin/accounts/{id}
GET /api/v1/admin/accounts/{id}/usage
GET /api/v1/admin/accounts/{id}/stats
GET /api/v1/admin/accounts/{id}/today-stats
POST /api/v1/admin/accounts/today-stats/batch
```

注意：这里的 `accounts` 是 Sub2API 上游模型账号/渠道账号，不是用户账号。

兑换码：

```http
GET /api/v1/admin/redeem-codes
POST /api/v1/admin/redeem-codes/generate
POST /api/v1/admin/redeem-codes/create-and-redeem
```

## 6. 鉴权方式

Admin API 推荐：

```http
x-api-key: <admin-api-key>
```

也支持管理员 JWT：

```http
Authorization: Bearer <admin-jwt>
```

后台自动化优先用 Admin API Key，不依赖管理员网页登录态。

## 7. 推荐事件源

| 优先级 | 表 | 条件 | 幂等键 |
|---|---|---|---|
| 1 | `payment_orders` | `order_type = 'balance'` 且 `status = 'COMPLETED'` | `sub2api.payment_orders:{id}` |
| 2 | `redeem_codes` | `status = 'used'` 且 `used_by` 不为空 | `sub2api.redeem_codes:{id}` |
| 3 | `payment_audit_logs` | 辅助识别订单动作和处理状态 | `order_id + action` |
| 4 | `users` 快照 | 仅对账和人工确认，不能自动发放返利 | `sub2api.user_balance_snapshot:{user_id}:{time}` |

用户资料展示额外读取：

| 表 | 字段 | 用途 |
|---|---|---|
| `user_affiliates` | `user_id`、`aff_code`、`inviter_id` | 给 Sub2Rebate 用户资料挂 Sub2API 原生邀请码/邀请链接；`inviter_id` 只做迁移或对账参考 |

注意：`users.balance` 会被充值、使用扣费、退款、管理员调额同时影响，而且额度会持续减少，不能单独作为返利事件依据。余额快照只能用于对账、异常提示或人工确认。

Sub2API 原生分享链接按配置模板生成，当前源码前端使用：

```text
https://api.sjiaa.cc.cd/register?aff={aff_code}
```

Sub2API 的 `/affiliate` 是登录后查看原生邀请返利页面，不作为 Sub2Rebate 多级返利树页面。

## 8. 当前落地状态

- 已实现 Sub2API 用户读取适配层：`backend/app/Modules/Sub2Api/Repositories/Sub2ApiUserRepository.php`。
- 已实现 Sub2API `user_affiliates` 读取：左联读取 `aff_code`、`inviter_id`。
- 已实现 Sub2API bcrypt 密码校验适配：`Sub2RebateAuthService`。
- 已实现登录后本地用户同步和 Sanctum token 签发。
- 本地 `users` 快照会同步保存 `sub2api_aff_code` 和 `sub2api_inviter_id`。
- 已实现账户资料接口聚合 Sub2API 原生邀请链接。
- 已实现 Sub2API Admin API client：`Sub2ApiAdminClient`，使用 `x-api-key` 调用上游账号监控接口。
- 已新增 `sub2api_upstream_accounts` 本地监控快照表，只保存上游模型账号/渠道账号最近一次状态、原始数据和最近错误。
- 已实现 `Sub2ApiUpstreamAccountSyncService`，支持同步上游账号列表、详情、用量、统计和今日统计；失败只写审计日志，不影响登录、返利、提现。
- 已实现 Filament 上游账号监控页面，支持手动同步列表、同步详情和查看原始统计数据。

待实现：

- 充值事件扫描器。
- `payment_orders`、`redeem_codes`、`payment_audit_logs` 到 `rebate_events` 的幂等写入。
- 扫描游标表或配置项。
- 生产库结构复核脚本。
- 真实 Sub2API 登录联调。
- Sub2API 手动加额度的统一入口策略：建议收敛到 Sub2Rebate 后台补录或兑换码/订单入口。

## 9. 开发记录

### 2026-06-13

- 落地 B1/B2 首版后端代码：模块 ServiceProvider、Sub2API 用户只读 Repository、登录适配、Sanctum token、用户资料接口。
- 用户资料接口已返回 Sub2Rebate 邀请码/邀请链接与 Sub2API `aff_code`/原生邀请链接。
- 项目内已安装 PHP/Composer 工具链，运行时依赖和 PHPUnit dev 依赖已可用；PHP 语法检查、临时 sqlite 迁移、服务层 smoke 和 HTTP smoke 通过。
- 完整 `composer install` 已完成，`php artisan test` 通过：6 个测试，32 个断言。

### 2026-06-13

- 完成 Sub2API 接口、源码、数据库迁移和管理鉴权调研。
- 确认公网入口可访问。
- 通过 SSH 只读复核生产库表结构和 `admin/users` 返回结构。
- 明确 `admin/users` 是用户账号体系，`admin/accounts` 是上游账号监控。
- 按用户要求开启 Sub2API 原生 affiliate：`affiliate_enabled = true`，返利比例 20%，现有 2 个用户已补齐 `user_affiliates` 档案。
- 验证 `/api/v1/settings/public` 返回 `affiliate_enabled: true`，`/affiliate` 路径返回 `HTTP 200`。
- 补充策略：Sub2Rebate 读取 `user_affiliates.aff_code` 并挂到用户资料，用于展示 Sub2API 原生邀请链接；多级邀请树仍由 Sub2Rebate 自己维护。
- 确认 Admin API 以 `x-api-key` 作为稳定自动化鉴权方式。
- 确认共享数据库方案可行，建议创建专用只读库账号。
- 修正集成策略：优先扫描 `payment_orders`、`redeem_codes`、`payment_audit_logs`，不再把 `users.balance` 当作第一事件源。

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确集成边界和优先方案。
- 下一步：拿到 Sub2API 数据库结构后细化适配方案。

### 2026-06-13

- 目标：落地 B12 Sub2API 上游账号监控首版。
- 完成：新增 Admin API 配置、`Sub2ApiAdminClient`、上游账号快照表、同步服务和 Filament 监控页面。
- 完成：页面和文档明确 `admin/accounts` 是上游模型账号/渠道账号，不是用户账号。
- 完成：失败只影响监控页面，写入 `audit_logs`；上游账号数据不写入 `rebate_events`，不参与返利计算。
- 验证：`Sub2ApiUpstreamAccountTest` 覆盖 `x-api-key` 请求头、列表/详情同步、失败审计和不写入返利事件。
