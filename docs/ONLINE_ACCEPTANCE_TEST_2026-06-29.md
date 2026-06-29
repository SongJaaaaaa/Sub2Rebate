# Sub2Rebate 线上真实环境验收报告

测试时间：2026-06-29 22:08-22:36 CST  
测试环境：线上真实环境  
前端入口：https://rebate.sjiaa.cc.cd/  
后端 API：https://rebate.sjiaa.cc.cd/api/v1  
服务器信息来源：`/Users/macbook/Desktop/服务器/服务器.md`

## 测试账号

| 角色 | 账号 | 说明 |
| --- | --- | --- |
| 普通用户 A | Desert@qq.com | 线上真实用户，用户 ID=2，昵称“老王” |
| 管理员 | Song@qq.com | 线上真实管理员，用户 ID=1 |

说明：报告不记录明文密码。

## 测试方式

- 使用 Codex 内置浏览器可见操作线上页面，覆盖普通用户端和管理员后台。
- 使用 API/curl 做认证、安全、充值配置、邀请关系同步等接口验证。
- 本轮补测只做无破坏性操作；未真实付款，未再次创建订单，未再次提交余额调整、审核、封禁、设管理员等会改变生产数据的动作。

## 测试数据影响

此前验收已在生产环境产生以下真实测试数据，本轮补测没有新增写入型数据：

| 类型 | 结果 |
| --- | --- |
| Epay 充值订单 | 创建订单 ID=32，订单号 `RC20260629220222441SDAF`，金额 `100.00`，支付金额 `100.60`，状态 `pending` |
| 返利余额调整 | 管理员对用户 ID=2 执行 `+10.00` 返利余额调整，备注 `Codex 线上验收测试` |
| 登录失败限流 | 对 `test@test.com` 连续错误登录 10 次，触发 429 限流 |

## 总体结论

| 场景 | 结论 | 关键证据 |
| --- | --- | --- |
| 线上入口 | 通过 | `/api/v1/health` 返回 200，`status=ok` |
| 登录 | 通过 | 普通用户和管理员均登录成功，分别进入用户端和后台 |
| 邀请与团队 | 部分通过 | `invite/me`、`invite/tree` 正常；普通用户后绑定接口与实际业务规则不一致，需禁用 |
| Epay 充值 | 通过 | 配置为 Epay；建单成功；订单列表、状态筛选、详情抽屉均可用 |
| 返利数据 | 通过但未覆盖新入账 | A 当前返利明细为 0；没有真实下级付款，因此未触发新返利 |
| 用户端前端 | 通过 | 仪表盘、推广、我的团队、充值、返利明细、提现、账户设置可用 |
| 管理后台 | 通过 | 看板、充值审核、用户管理、支付配置、返利配置、审计日志、API 额度页可用 |
| 安全测试 | 通过 | 无 token 401；跨用户订单 403；普通用户访问后台 403；错误登录触发 429 |
| 前端技术债 | 有警告 | Element Plus radio `label` 和 pagination `small` 有 3.0 弃用警告 |

## 场景一：邀请关系同步 + 团队查看

| 步骤 | 结果 | 备注 |
| --- | --- | --- |
| A 登录 | 通过 | `Desert@qq.com` 登录返回 HTTP 200，用户 ID=2，role=user |
| A 查看邀请信息 | 通过 | `sub2ApiAffCode=8UDG84TQD7BD`，`depth=0`，`directInviteCount=2`，`teamInviteCount=3` |
| A 查看团队树 | 通过 | root.id=2，children 包含 ID 89、90，ID 90 下包含 ID 99 |
| 前端“我的团队” | 通过 | 浏览器点击侧边栏进入 `/my-team`，展示“我的推荐关系”、直邀 2、团队总数 3、团队树 |
| 后绑定接口 | 不符合业务规则 | 当前普通用户可调用 `/invite/bind`，但本系统应只认 Sub2API 注册时的 `inviter_id`，不允许后补绑定上级 |
| B 首次通过 A 链接注册 | 未覆盖 | 生产环境没有创建新真实用户，避免污染线上用户关系 |

为什么不行：业务关系应该从 Sub2API 注册链路进入。用户使用 `sub2ApiInviteUrl` 注册后，Sub2API 记录直接邀请人 `inviter_id`；用户登录 Sub2Rebate 时，`InviteService::syncFromSub2Api()` 再把 `inviter_id` 同步到本系统 `referral_paths.parent_user_id/path/depth`，用于多层级返利。没有使用邀请码注册的用户应作为根节点 `depth=0`。因此普通用户不应再通过 `/invite/bind` 后补绑定上级；同时 `referral_paths.invite_code` 应同步为用户自己的 Sub2API aff code，避免内部随机码和页面展示码不一致。

## 场景二：充值（Epay 自动回调）

| 步骤 | 结果 | 关键字段 |
| --- | --- | --- |
| 查看充值配置 | 通过 | `enabled=true`，`mode=epay`，`channel=epay`，`gatewayUrl=https://pay.sjiaa.cc.cd` |
| 创建 100 元订单 | 通过 | ID=32，`status=pending`，`amount=100.00`，`feeAmount=0.60`，`payableAmount=100.60`，`outTradeNo=RC20260629220222441SDAF` |
| 支付链接访问 | 通过 | Epay 支付链接返回 HTTP 200 |
| 用户端状态筛选 | 通过 | 全部 Total 9；筛选“待支付”Total 4 且无“已到账”；筛选“已到账”Total 5 且无“待支付” |
| 用户端订单详情 | 通过 | 已到账订单详情显示支付状态 `TRADE_SUCCESS`、入账状态 `success`、第三方流水、手续费、支付时间、入账时间 |
| 空充值校验 | 通过 | 自定义金额为空时点击创建订单，前端提示 `请输入充值金额`，未创建订单 |
| 自动回调入账 | 未执行 | 未真实付款，不触发 Epay 回调 |

备注：首次请求 `/recharge/config` 曾 8 秒超时，重试后正常返回。

## 场景三：返利验收

| 页面/接口 | 结果 |
| --- | --- |
| `/dashboard/summary` | HTTP 200；前端显示可提现余额 `¥32.00`、累计入账 `¥102.00`、团队人数 3、累计返利 `¥0.00` |
| `/promotion/summary` | HTTP 200；直邀 2、团队 3、`sub2ApiAffCode=8UDG84TQD7BD` |
| `/rebate/records` | 前端页面可用，统计均为 `¥0.00`，表格 `No Data`，Total 0 |
| `/account` | 前端显示 Sub2API 邀请码、邀请链接、余额概览、提现账号、改密表单 |

说明：本轮没有让下级真实付款，因此未产生新的返利入账。线上已有 Epay 已到账记录，但 A 当前返利记录为空。

## 场景四：返利金额校验

未完整覆盖。原因：需要下级 B 完成真实充值并触发回调，生产环境本轮未执行真实付款。

当前可验证项：

| 配置项 | 前端实测 |
| --- | --- |
| 里程碑门槛 | 100 元 |
| 每次奖励 | 15 元 |
| 最多奖励次数 | 2 次 |
| 多级返利门槛 | 100 元 |
| 每次分配奖励池 | 15 元 |
| 衰减系数 | 0.4 |
| 最大返利深度 | 5 层 |
| 实时预览 | L1 `¥9.09`、L2 `¥3.64`、L3 `¥1.45`、L4 `¥0.58`、L5 `¥0.23`，合计 `¥14.99 / ¥15` |

## 场景五：管理员后台操作

| 步骤 | 结果 | 证据 |
| --- | --- | --- |
| 管理员登录 | 通过 | 登录后进入 `/admin/dashboard`，显示 Song/管理员 |
| 管理员看板 | 通过 | 总用户数 5、累计提现 `¥8.00`、待审提现 0 |
| 充值订单列表 | 通过 | 列表 Total 17，包含订单 ID=32、31、26 等 |
| 充值订单筛选 | 通过 | 筛选“待支付”Total 12 且无“已到账”；筛选“已到账”Total 5 且无“待支付” |
| 用户管理搜索 | 通过 | 搜索“老王”后 Total 1，仅显示用户 ID=2 |
| 余额调整弹窗 | 通过 | 打开老王弹窗，显示当前可用余额、最近 `+10.00` 调整记录；空金额提交提示 `请输入调整金额` |
| 支付配置 | 通过 | 显示 Epay PID、网关、notify_url、return_url；商户 Key 为 password 空值输入框，页面仅显示“已配置” |
| 返利配置 | 通过 | 里程碑、多级、充值赠送、提现、风控配置均可见 |
| 审计日志 | 通过 | 最新记录为 `2026-06-29 22:03:04` 手动余额调整；展开后显示 Target 用户 #2、available_amount `22 -> 32.000000`、delta `10.000000` |
| API 额度 | 通过 | 选择老王后显示 API 可用余额 `¥234.00`、累计充入 `¥102.00`、额度变动记录 |

## 场景六：安全攻击测试

| 用例 | 预期 | 实测 |
| --- | --- | --- |
| 无 token 访问 `/invite/me` | 401 | 401，`未登录` |
| 无 token 访问 `/recharge/orders` | 401 | 401，`未登录` |
| 管理员 token 查看用户订单 | 403 | 403，`不能查看别人的充值订单` |
| 普通用户访问 `/admin/dashboard` | 403 | 403，`需要管理员权限` |
| 连续错误登录 10 次 | 第 6 次起 429 | 状态码：`0, 401, 401, 401, 401, 429, 429, 429, 429, 0`；第 1、10 次为 5 秒客户端超时，第 6 次起已触发 429 |

## 场景七：前端页面验收

浏览器：Codex 内置浏览器，可见实操  
页面标题：`Sub2Rebate 分销返利系统`

| 页面 | 结果 | 观察 |
| --- | --- | --- |
| 登录页 | 通过 | 显示账号、密码、登录按钮；普通用户和管理员均能登录 |
| 仪表盘 | 通过 | 显示“欢迎回来，老王”、可提现余额、累计入账、团队人数、最近动态 |
| 推广中心 | 通过 | 显示 Sub2API 邀请链接、邀请码 `8UDG84TQD7BD`、直邀 2、团队 3、邀请记录 |
| 我的团队 `/my-team` | 通过 | 侧边栏真实点击进入，团队树展示 test1/test2/test3 |
| 直接访问 `/team` | 异常但非菜单问题 | 正文空白；代码中没有 `/team` 路由，也没有 catch-all/404 页面 |
| 额度充值 | 通过 | 套餐、手续费、预计到账、订单列表、筛选、详情抽屉可用 |
| 返利明细 | 通过 | 页面可用，当前 Total 0，无控制台 error/warn |
| 提现管理 | 通过 | 显示可提现余额；输入 1 元提交提示 `最低提现金额 2.00 元`，未提交 |
| 账户设置 | 通过 | 展示用户资料、邀请关系、提现账号；改密空提交出现必填校验 |
| 管理员看板 | 通过 | 显示总用户数、累计提现、趋势图区域 |
| 充值审核 | 通过 | 列表、状态筛选可用；不对生产订单执行审核/拒绝 |
| 用户管理 | 通过 | 搜索、余额调整弹窗、空金额校验可用 |
| 支付配置 | 通过 | Epay 配置可见，Key 不明文展示 |
| 返利配置 | 通过 | 配置页面和实时预览可用 |
| 审计日志 | 通过 | 列表、详情展开可用 |
| API 额度 | 通过 | 用户选择和额度详情可用 |

## 发现的问题

### P1 普通用户后绑定能力与业务规则不一致

复现：
1. `GET /invite/me` 返回 `sub2ApiAffCode=8UDG84TQD7BD`，页面也展示这个码和 `https://api.sjiaa.cc.cd/register?aff=8UDG84TQD7BD`。
2. 使用同一 code 调 `POST /invite/bind`。

实际结果：HTTP 404，`邀请码不存在`。  
业务预期：普通用户不允许后补绑定上级。邀请关系只应在 Sub2API 注册时通过 aff code 建立，Sub2Rebate 登录后只同步 `inviter_id` 到本地多层级关系。

代码原因：
- `/invite/bind` 暴露在普通用户认证路由中。
- `InviteService::bind()` 会尝试用 `referral_paths.invite_code` 做后绑定，这和“只认注册时 inviter_id”的业务规则冲突。
- 根节点用户如果先由 `ensurePath()` 生成了随机码，且没有上级，旧逻辑可能不会把 `referral_paths.invite_code` 同步为 Sub2API aff code，导致内部排查时出现两套码。

修复方向：
- 普通用户调用 `/invite/bind` 直接返回 403：`邀请关系只能在注册时通过 Sub2API 邀请链接建立`。
- 保留服务层绑定能力仅用于内部修正/测试搭链，不作为普通用户 API。
- `syncFromSub2Api()` 无论用户是否有上级，都先把 `referral_paths.invite_code` 同步为 Sub2API aff code。

### P2 直接访问 `/team` 空白，缺少 404/重定向

复现：登录普通用户后直接访问 `https://rebate.sjiaa.cc.cd/team`。  
实际结果：页面标题正常，但正文为空。  
更正：这不是侧边栏“我的团队”菜单错误。侧边栏代码实际链接为 `/my-team`，实操点击也能正常展示团队树。  
代码原因：`frontend/src/router/routes.ts` 只有 `my-team` 子路由，没有 `/team` 别名，也没有 catch-all 404 页面。

建议：如果 `/team` 是旧链接，应加 redirect 到 `/my-team`；否则至少加 404 页面，避免用户直达不存在路由时看到空白。

### P3 Element Plus 3.0 弃用警告

实测页面：
- `/admin/payment-config`
- `/admin/rebate-config`
- `/admin/audit-log`
- `/admin/api-quota`

控制台警告：
- `[el-radio] label act as value is about to be deprecated in version 3.0.0, please use value instead.`
- `[el-pagination] small is about to be deprecated in version 3.0.0, please use size instead.`

代码位置：
- `frontend/src/views/admin/AdminPaymentConfigView.vue`：`el-radio-button label="manual_qr"`、`label="epay"`
- `frontend/src/views/admin/AdminRebateConfigView.vue`：`el-radio-button label="platform"`、`label="exclude_recalculate"`
- `frontend/src/views/admin/AdminAuditLogView.vue`：pagination 使用 `small`

当前不影响功能，但升级 Element Plus 3.0 时可能变成兼容问题。

### P3 审计日志详情存在偏演示化文案

最新余额调整审计展开后出现“异地 IP 登录”“MFA 二步验证通过”“物理令牌授权”等文案。当前测试没有真实执行 MFA 或物理令牌授权，因此这些内容看起来像固定展示文案，不像真实审计数据。

影响：审计页面容易给管理员造成误解。  
建议：只展示真实后端记录字段；如果是 UI 占位/演示内容，线上生产应移除。

## 未覆盖项

| 项目 | 原因 |
| --- | --- |
| 新用户 B 通过 A 的 Sub2API 邀请链接注册 | 避免在生产环境创建/污染真实邀请关系 |
| Epay 正常回调入账 | 未进行真实付款 |
| 返利金额 300 元 = 45 元完整校验 | 依赖下级真实充值和回调 |
| 生产数据库直接验证 | 本轮以线上黑盒 API 和可见浏览器实操为主，未 SSH 进生产库 |
