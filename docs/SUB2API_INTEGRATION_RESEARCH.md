# Sub2API 集成调研报告

日期：2026-06-13

## 1. 调研结论

Sub2Rebate 与当前 Sub2API 的集成在接口和源码层面可行。

推荐第一版采用：

```text
Sub2API PostgreSQL 只读共享
-> 扫描充值/兑换/支付审计记录
-> 生成 Sub2Rebate rebate_events
-> 队列计算返利

Sub2API Admin API
-> 使用 x-api-key
-> 查询上游账号、上游账号用量、上游账号统计等运营数据
```

不推荐把 `users.balance` 的变化直接当作返利事件。Sub2API 使用扣费也会修改余额，直接监控余额差值会把消费扣款和充值混在一起，只能作为对账快照或人工核对线索。

生产服务器已通过 SSH 做只读复核：线上库存在 `users`、`auth_identities`、`payment_orders`、`redeem_codes`、`payment_audit_logs`、`accounts` 等关键表；`admin/users` 和 `admin/accounts` Admin API 均可访问。

## 2. 服务器记录核验

来自 `/Users/macbook/Desktop/服务器/服务器.md` 的 Sub2API 相关入口：

| 项目 | 值 |
|---|---|
| 主 HTTPS 入口 | `https://api.sjiaa.cc.cd/` |
| 备用直连入口 | `http://154.44.9.60:8080/` |
| GitHub 源码 | `https://github.com/Wei-Shaw/sub2api` |
| 部署目录记录 | `/opt/sub2api-deploy` |
| 相关容器记录 | `sub2api`、`sub2api-postgres`、`sub2api-redis` |

2026-06-13 已用 HEAD 请求核验：

- `https://api.sjiaa.cc.cd/` 返回 `HTTP 200`。
- `http://154.44.9.60:8080/` 返回 `HTTP 200`。

说明 Sub2API Web/API 服务当前公网入口在线。

2026-06-13 已直接 SSH 到服务器执行只读核验：

- Docker 容器存在：`sub2api`、`sub2api-postgres`、`sub2api-redis`。
- PostgreSQL 容器镜像：`postgres:18-alpine`。
- PostgreSQL 用户/库名：`sub2api` / `sub2api`。
- 生产库共 74 张表。
- `users` 当前 2 个用户：1 个 `admin`、1 个 `user`，状态均为 `active`。
- `auth_identities` 当前只有 `email` provider，说明第一版按邮箱密码共用用户体系可行。
- `redeem_codes` 当前已有已使用记录；`payment_orders` 和 `payment_audit_logs` 当前为空，未来接支付后再作为主事件源。

## 3. 源码依据

本次调研重新 clone 官方源码到 `/private/tmp/sub2api-src`，源码版本：

```text
Wei-Shaw/sub2api @ e34ad2b
```

关键依据：

- 管理鉴权：`backend/internal/server/middleware/admin_auth.go`
- 登录路由：`backend/internal/server/routes/auth.go`
- 用户路由：`backend/internal/server/routes/user.go`
- 管理路由：`backend/internal/server/routes/admin.go`
- 初始表结构：`backend/migrations/001_init.sql`
- 支付订单：`backend/migrations/092_payment_orders.sql`
- 支付审计：`backend/migrations/093_payment_audit_logs.sql`
- 邀请返利：`backend/migrations/130_add_user_affiliates.sql` 及后续 affiliate migrations
- Admin CLI 文档：`skills/sub2api-admin/references/admin-cli.md`

## 4. 登录与鉴权方案

### 用户登录

Sub2API 登录接口：

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password",
  "turnstile_token": ""
}
```

源码中的登录响应包含：

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {}
}
```

当前 Sub2Rebate 第一版仍建议自己签发 Laravel Sanctum token：读取 Sub2API `users` 表校验账号密码和角色后，在 Sub2Rebate 内维护会话。这样不依赖 Sub2API JWT 生命周期，也方便独立控制分销系统后台权限。

源码确认 `users.password_hash` 使用 bcrypt，生成方式为 `bcrypt.GenerateFromPassword(..., bcrypt.DefaultCost)`，校验方式为 `bcrypt.CompareHashAndPassword`。Laravel 侧可用 bcrypt verifier 适配。

### Admin API

Sub2API 管理接口支持两种鉴权：

```http
x-api-key: <admin-api-key>
```

或：

```http
Authorization: Bearer <admin-jwt>
```

源码与 Admin CLI 文档都显示，后台自动化优先使用 `x-api-key`。如果返回 `INVALID_ADMIN_KEY`，需要在 Sub2API 后台重新生成管理员 API Key。

## 5. 已确认接口

### 用户账号

用于获取真正的用户账号体系：

```http
GET /api/v1/admin/users
GET /api/v1/admin/users/{id}
POST /api/v1/admin/users/{id}/balance
GET /api/v1/admin/users/{id}/balance-history
GET /api/v1/auth/me
GET /api/v1/user/profile
```

生产接口复核结果：

- `GET /api/v1/admin/users?page=1&page_size=10` 返回 `code = 0`。
- 返回分页结构：`items`、`total`、`page`、`page_size`、`pages`。
- 用户字段包含：`id`、`role`、`status`、`balance`、`total_recharged`、`concurrency`、`username`、`created_at`、`updated_at` 等。
- 用户敏感字段如邮箱和密码哈希不应写入日志或项目文档。

### 上游账号与用量

这里的 `accounts` 不是用户账号，而是 Sub2API 管理的上游模型账号/渠道账号。它和 Sub2Rebate 用户账号体系没有直接关系，只适合做运营监控：

```http
GET /api/v1/admin/accounts
GET /api/v1/admin/accounts/{id}
GET /api/v1/admin/accounts/{id}/usage
GET /api/v1/admin/accounts/{id}/stats
GET /api/v1/admin/accounts/{id}/today-stats
POST /api/v1/admin/accounts/today-stats/batch
```

服务器记录中的账号监控器已记录使用：

```http
GET /api/v1/admin/accounts/{id}/usage?source=active&force=true&timezone=Asia/Shanghai
```

这类接口适合做运营监控、账号额度观察、异常账号提示；不适合作为用户充值返利的唯一事件源。

生产接口复核结果：

- `GET /api/v1/admin/accounts?page=1&page_size=1` 返回 `code = 0`，当前上游账号总数为 10。
- `GET /api/v1/admin/accounts/{id}/usage?source=active&force=true&timezone=Asia/Shanghai` 返回 `code = 0`，数据结构包含 `updated_at`、`five_hour`、`seven_day`。
- `GET /api/v1/admin/accounts/{id}/stats?days=30` 返回 `history`、`summary`、`models`、`endpoints`、`upstream_endpoints`。
- `GET /api/v1/admin/accounts/{id}/today-stats` 返回 `requests`、`tokens`、`cost`、`standard_cost`、`user_cost`。

注意：源码里 `UpdateBalance` 最终会更新 `users.balance`，正数还会增加 `users.total_recharged`。但没有看到稳定的独立余额变动流水表，因此管理员手动调额不能只靠余额字段做幂等返利。

### 兑换码与支付

```http
GET /api/v1/admin/redeem-codes
GET /api/v1/admin/redeem-codes/{id}
POST /api/v1/admin/redeem-codes/generate
POST /api/v1/admin/redeem-codes/create-and-redeem

GET /api/v1/payment/orders/my
GET /api/v1/admin/payment/orders
```

这些更适合作为充值事件来源，尤其是 `payment_orders`、`redeem_codes`、`payment_audit_logs` 能提供订单、兑换、审计和幂等线索。

## 6. 共享数据库表结构

源码迁移中确认的关键表：

| 表 | 用途 | 集成建议 |
|---|---|---|
| `users` | 用户、角色、余额、累计充值 | 登录校验、用户映射、余额快照 |
| `redeem_codes` | 卡密/兑换码 | 兑换充值事件源之一 |
| `payment_orders` | 支付订单 | 充值事件主来源 |
| `payment_audit_logs` | 支付审计动作 | 幂等和订单动作判定 |
| `user_affiliates` | Sub2API 原生邀请关系、`aff_code` | 读取 `aff_code` 生成 Sub2API 邀请链接；`inviter_id` 只做迁移/对账参考 |
| `user_affiliate_ledger` | Sub2API 原生邀请返利流水 | 可读参考，不直接复用为本系统流水 |
| `auth_identities` | 用户登录身份 | 当前线上只有 `email`，可辅助判断登录来源 |
| `accounts` | 上游模型账号/渠道账号 | 只做运营监控，和用户账号体系不要混用 |
| `usage_logs` | API 使用扣费记录 | 用量分析，不作为充值事件 |

第一版建议给 Laravel 配置一个 `sub2api` 只读连接，当前后端骨架已预留：

```text
SUB2API_DB_HOST=
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=sub2api
SUB2API_DB_USERNAME=
SUB2API_DB_PASSWORD=
```

## 7. 充值事件推荐来源

优先级：

1. `payment_orders`
   - 条件：`order_type = 'balance'` 且 `status = 'COMPLETED'`。
   - 金额：优先使用 `amount`，按业务需要换算人民币或美元口径。
   - 幂等键：`sub2api.payment_orders:{id}`。

2. `redeem_codes`
   - 条件：`status = 'used'` 且 `used_by` 不为空。
   - 金额：`value`。
   - 幂等键：`sub2api.redeem_codes:{id}`。

3. `payment_audit_logs`
   - 用途：辅助判断订单动作是否已处理，或识别 Sub2API 自带 affiliate 相关动作。
   - 幂等辅助：`order_id + action`。

4. `users.balance` / `users.total_recharged` 快照
   - 仅用于对账、异常提示和人工确认。
   - 余额会被消费扣款、退款、管理员调额等影响，不能直接自动触发返利。
   - 如果管理员手动加额度需要返利，应统一走兑换码/订单/Sub2Rebate 后台补录入口。

## 8. Sub2API 原生邀请返利边界

Sub2API 自带的 affiliate 机制是单级邀请返利：

- `user_affiliates.aff_code` 是用户的 Sub2API 原生邀请返利码。
- `user_affiliates.inviter_id` 是直接邀请人。
- `user_affiliate_ledger.action` 包含 `accrue`、`transfer`。
- 支持全局或用户专属返利比例。

它不等于 Sub2Rebate 要做的无限级衰减金字塔。Sub2Rebate 不应把 Sub2API 原生 affiliate 当作完整业务核心，只能作为迁移参考或旧系统对账来源。

但 Sub2Rebate 需要读取并绑定展示 Sub2API 原生邀请链接：

- 后端通过 `users.id = user_affiliates.user_id` 聚合用户资料。
- 返回 `sub2ApiAffCode`，并按配置模板生成 `sub2ApiInviteUrl`。
- 当前 Sub2API 前端实际分享入口为 `https://api.sjiaa.cc.cd/register?aff={aff_code}`，用户入口页为 `https://api.sjiaa.cc.cd/affiliate`。
- 该链接只表示 Sub2API 原生单级邀请入口，不参与 Sub2Rebate 多级返利金额计算。

2026-06-13 已按用户要求在线开启 Sub2API 原生 affiliate：

- `affiliate_enabled = true`。
- `affiliate_rebate_rate = 20`。
- `affiliate_rebate_freeze_hours = 0`。
- `affiliate_rebate_duration_days = 0`，表示永久有效。
- `affiliate_rebate_per_invitee_cap = 0`，表示无单人上限。
- 已为现有 2 个用户补齐 `user_affiliates` 档案，邀请码长度均为 12 位。
- `GET /api/v1/settings/public` 已返回 `affiliate_enabled: true`。
- `GET /api/v1/admin/affiliates/users/{id}/overview` 已对现有用户返回 `code = 0`。
- `/affiliate` 前端路径返回 `HTTP 200`。

注意：这只是开启 Sub2API 原生单级邀请返利页面和逻辑。Sub2Rebate 第一版仍要独立维护无限级邀请树、返利事件和返利余额，最多把 Sub2API 原生 affiliate 作为旧数据参考。

## 9. 推荐落地方案

### 数据库

- Sub2Rebate 自己维护业务表：邀请树、返利事件、返利流水、提现、配置、审计。
- Sub2API 数据库只读接入，读取 `users`、`auth_identities`、`user_affiliates`、`payment_orders`、`redeem_codes`、`payment_audit_logs`。
- 用户资料聚合时，把 `user_affiliates.aff_code` 挂到 Sub2Rebate 用户资料响应上；本系统多级树仍以 `referral_paths` 为准。
- 禁止第一版直接写 Sub2API 核心表，除非是明确的管理动作并且走 Sub2API Admin API。

### 定时任务

Laravel Scheduler 每 1 到 5 分钟扫描：

```text
payment_orders.completed_at > last_cursor
redeem_codes.used_at > last_cursor
payment_audit_logs.created_at > last_cursor
```

扫描结果写入 Sub2Rebate：

```text
rebate_events.source_type
rebate_events.source_id
rebate_events.user_id
rebate_events.amount
rebate_events.occurred_at
```

`source_type + source_id` 必须唯一，防止重复发放。

### Admin API

Sub2Rebate 可配置：

```text
SUB2API_BASE_URL=https://api.sjiaa.cc.cd
SUB2API_ADMIN_API_KEY=
```

用途：

- 拉取上游账号列表。
- 查询上游账号用量。
- 查询上游账号统计。
- 必要时对接运营看板。

## 10. 后续生产建议

生产库结构已经完成只读复核。后续开发前建议补一件事：

1. 创建一个 Sub2API PostgreSQL 只读账号给 Sub2Rebate 使用。
2. 不要让 Sub2Rebate 使用容器主账号 `sub2api` 连接生产库。
3. Admin API Key 只用于运营监控，不用于用户登录。

建议只读账号权限：

```sql
GRANT CONNECT ON DATABASE sub2api TO sub2rebate_ro;
GRANT USAGE ON SCHEMA public TO sub2rebate_ro;
GRANT SELECT ON users, auth_identities, user_affiliates, redeem_codes, payment_orders, payment_audit_logs TO sub2rebate_ro;
```

如果后续要做上游账号运营看板，再额外授予：

```sql
GRANT SELECT ON accounts, usage_logs TO sub2rebate_ro;
```

不要把 Admin API Key、数据库密码或账号密码写入项目文档。

## 11. 最终判断

| 项目 | 判断 |
|---|---|
| Sub2API 公网入口 | 可访问 |
| 用户账号共用 | 可行，共用 `users` / `auth_identities` |
| Sub2API 邀请链接展示 | 可行，读取 `user_affiliates.aff_code` 并生成 `/register?aff=` 链接 |
| 用户登录方案 | 可行，Sub2Rebate 校验 Sub2API bcrypt 密码后自签 token |
| 上游账号监控 | 可行，使用 `/admin/accounts`，但它不是用户账号 |
| 上游账号用量监控 | 可行，使用 `/admin/accounts/{id}/usage` |
| 共享数据库 | 可行，已创建并验证 `sub2rebate_ro` 专用只读连接 |
| 当前充值返利事件源 | 当前优先 `redeem_codes`，未来接支付后加 `payment_orders` |
| 直接监控余额字段 | 不推荐，只能对账和人工确认，不能自动返利 |
| Sub2API 原生 affiliate | 已开启，可用于 Sub2API `/affiliate`，不推荐作为 Sub2Rebate 主业务 |
| 生产库结构在线确认 | 已完成只读复核 |
