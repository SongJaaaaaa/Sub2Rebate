# Sub2Rebate 完整实施开发计划

> 本计划用于后续真正开工执行。前端、后端按任务线拆分，互不干涉；公共契约先定，最后联调验收。

## 1. 计划依据

本计划以当前项目文档和 UI 效果稿为准：

- `docs/PROJECT_STATE.md`
- `docs/PROJECT_GUIDELINES.md`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/modules/*/README.md`
- `stitch_sub2rebate_affiliate_management_system/**/screen.png`
- `stitch_sub2rebate_affiliate_management_system/precision_rebate_intelligence/DESIGN.md`

注意：

- 如果 `Sub2Rebate-返利系统深度总结.md` 与当前 ADR 冲突，以 `docs/DECISIONS.md` 和模块文档为准。
- 当前已定规则是衰减系数模式、无限级金字塔、里程碑默认 2 次。
- 效果稿部分文案是英文，实现时页面文案统一中文，布局、组件密度、色彩和交互风格参考效果稿。

## 2. 第一版目标

第一版完成最小可用业务闭环：

```text
复用 Sub2API 用户登录
-> 用户绑定邀请关系
-> 新人累计充值触发里程碑奖励
-> 里程碑结束后进入无限级衰减返利
-> 返利进入独立余额
-> 用户申请提现
-> 管理员人工审核和标记打款
-> 全过程有流水、配置、审计和基础风控
```

第一版必须做到：

- 金额计算可追溯。
- 返利事件幂等，不重复发放。
- 提现状态清晰，余额冻结和解冻可审计。
- 前后端通过明确 API 契约协作。
- Vue 前端按效果图完成用户端页面，Filament 后台按效果图完成管理端关键页面。
- 管理后台纳入 Sub2API 上游账号监控，用于查看模型账号/渠道账号状态和用量。
- 邀请树历史结构不可被用户删除、失联或防躺平判定自动打乱；失效节点在管理端置灰但下级不上浮。
- 账号登录状态和返利资格拆分：节点可登录不代表可继续获得返利。
- 多级返利遇到失效节点时，按后台配置选择“归平台”或“排除失效节点后重算”。
- 每个阶段完成后同步模块文档、开发日志、项目状态。

第一版暂不做：

- 支付宝自动充值。
- 支付宝自动提现。
- 贡献系数自动影响返利。
- 活动多规则并行。
- 推广员等级体系。
- 复杂异常检测模型。

## 3. 任务线拆分

任务按 4 条线执行：

| 任务线 | 负责人类型 | 目标 | 是否可并行 |
|---|---|---|---|
| S 公共契约 | 全栈/架构 | 定接口、状态、错误码、Mock 数据、联调口径 | 必须先启动 |
| B 后端任务线 | 后端 | Laravel API、模块、数据库、返利引擎、Filament | 可与前端并行 |
| F 前端任务线 | 前端 | Vue 用户端、页面布局、状态、API 接入、效果图还原 | 可与后端并行 |
| I 联调验收 | 全栈 | 前后端联调、数据闭环、测试、部署检查 | 依赖 B/F |

互不干涉规则：

- 后端只改 `backend/`、后端相关文档和公共契约文档。
- 前端只改 `frontend/`、前端相关文档和公共契约文档。
- 公共契约修改必须先写清接口变更，再由前后端分别适配。
- 前端在后端未完成前使用 Mock API，不直接依赖后端开发进度。
- 后端不按前端页面临时拼接口，接口按业务资源和契约设计。

## 4. 目标目录结构

项目最终推荐结构：

```text
/
├── backend/                         Laravel 后端与 Filament 后台
├── frontend/                        Vue 3 用户前端
├── docker/                          部署编排
├── docs/                            长期项目文档
├── stitch_sub2rebate_affiliate_management_system/
│   └── ...                          UI 效果稿与设计 Token
├── README.md
└── Sub2Rebate-返利系统深度总结.md    历史需求总结，只作参考
```

## 5. 公共契约任务

### S0：冻结 MVP 范围

交付：

- 确认第一版必须做和暂不做。
- 把仍待确认的参数标记为配置项，不写死到业务代码。
- 明确旧文档冲突项不再作为开发依据。

验收：

- `docs/IMPLEMENTATION_PLAN.md` 已存在。
- `docs/PROJECT_STATE.md` 指向本计划。
- `docs/DEVELOPMENT_LOG.md` 记录本次计划落地。

### S1：API 契约文档

新增建议文件：

```text
docs/API_CONTRACT.md
```

必须包含：

- 统一响应格式。
- 认证方式。
- 用户端 API。
- 后台如需 API 的资源说明。
- 错误码。
- 状态枚举。
- 分页格式。
- 金额字段格式。

统一响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {}
}
```

分页响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [],
    "page": 1,
    "pageSize": 20,
    "total": 0
  }
}
```

金额字段约定：

- 后端返回字符串，例如 `"1280.00"`。
- 前端只展示，不参与关键金额计算。
- 前端提交金额使用字符串，例如 `"100.00"`。

第一版用户端 API 初稿：

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me

GET  /api/v1/dashboard/summary
GET  /api/v1/dashboard/rebate-trends
GET  /api/v1/dashboard/recent-activities

GET  /api/v1/invite/me
POST /api/v1/invite/bind
GET  /api/v1/invite/tree
GET  /api/v1/invite/records

GET  /api/v1/promotion/summary
GET  /api/v1/promotion/conversions
GET  /api/v1/rebate/records

GET  /api/v1/withdraw/config
GET  /api/v1/withdraw/account
POST /api/v1/withdraw/account
POST /api/v1/withdraw/apply
GET  /api/v1/withdraw/records

GET  /api/v1/account/profile
```

后台优先使用 Filament Resource，不强行暴露管理端 API；如需自定义接口，必须补进 `docs/API_CONTRACT.md`。

### S2：Mock 数据

新增建议目录：

```text
frontend/src/mocks/
```

Mock 覆盖：

- 登录用户。
- 用户仪表盘。
- 推广中心。
- 返利记录。
- 提现配置。
- 提现记录。
- 邀请树。

验收：

- 前端不依赖真实后端即可完成所有页面静态和交互开发。
- Mock 数据字段与 `docs/API_CONTRACT.md` 一致。

## 6. 后端架构目录规则

后端使用 Laravel 12 + Filament 3。业务代码按模块组织，不堆在 Controller 或 Model。

推荐目录：

```text
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Modules/
│   │   ├── Invite/
│   │   │   ├── Actions/
│   │   │   ├── Contracts/
│   │   │   ├── DTOs/
│   │   │   ├── Enums/
│   │   │   ├── Events/
│   │   │   ├── Jobs/
│   │   │   ├── Listeners/
│   │   │   ├── Models/
│   │   │   ├── Policies/
│   │   │   ├── Services/
│   │   │   └── Tests/
│   │   ├── Milestone/
│   │   ├── Rebate/
│   │   ├── Withdraw/
│   │   ├── Payment/
│   │   ├── Config/
│   │   ├── Risk/
│   │   ├── Audit/
│   │   └── Sub2Api/
│   ├── Providers/
│   └── Support/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php
│   └── console.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── composer.json
```

后端规则：

- Controller 只做鉴权、参数校验、调用 Action/Service。
- 金额计算必须在独立计算类中实现。
- 跨模块优先用 Event，其次用 Contract。
- 配置读取只通过 Config Service。
- 所有金额变更必须在事务中完成。
- 所有影响金额的事件必须有唯一键和幂等检查。
- 所有核心状态使用 Enum，不使用魔法字符串散落在代码里。
- 涉及充值、返利、提现、余额调整的接口必须校验用户权限，后台敏感操作必须要求管理员权限和备注。
- `users.balance` / `users.total_recharged` 只能用于对账、异常提示或人工确认，不能直接作为自动返利事件来源。

## 7. 后端数据表计划

第一版建议表：

| 表名 | 模块 | 用途 |
|---|---|---|
| `sub2_user_roles` | Admin/User | 标记 Sub2API 用户在本系统的角色 |
| `referral_paths` | Invite | 用户邀请码、直接上级、路径和深度 |
| `rebate_events` | Payment/Rebate | 明确充值/补录事件，幂等入口 |
| `user_rebate_progress` | Milestone | 被邀请人的里程碑进度 |
| `rebate_records` | Rebate/Audit | 每笔返利流水 |
| `rebate_balances` | Rebate/Withdraw | 用户独立返利余额 |
| `withdraw_accounts` | Withdraw | 用户提现账号 |
| `withdraw_records` | Withdraw | 提现申请、审核、打款状态 |
| `config_items` | Config | 系统配置项和 Tips |
| `risk_flags` | Risk | 用户风控状态、冻结、黑名单 |
| `audit_logs` | Audit | 配置、提现、余额、邀请关系等操作日志 |
| `payment_records` | Payment | 第一版保留支付/充值记录入口 |
| `sub2api_scan_cursors` | Sub2Api | 记录 `payment_orders`、`redeem_codes`、`payment_audit_logs` 扫描游标 |
| `sub2api_credit_snapshots` | Sub2Api | 余额快照对账和人工确认，不作为自动返利事件源 |

不单独复制 Sub2API 用户表。后端用户资料服务聚合 `users`、`auth_identities`、`user_affiliates` 和本地 `referral_paths`：

- `users` / `auth_identities`：登录、展示、角色映射。
- `user_affiliates.aff_code`：生成 Sub2API 原生邀请链接，例如 `https://api.sjiaa.cc.cd/register?aff={aff_code}`。
- `referral_paths`：Sub2Rebate 自己的多级邀请树和返利链路。

关键唯一约束：

- `referral_paths.user_id` 唯一。
- `referral_paths.invite_code` 唯一。
- `rebate_events.source_type + source_id` 唯一。
- `rebate_records.event_id + receiver_user_id + level` 唯一。
- `rebate_balances.user_id` 唯一。

金额字段：

- 使用 `decimal(18, 2)`。
- 比例字段使用 `decimal(8, 6)`。
- 不使用 float。
- 充值事件需要保存原始来源金额、标准金额、币种/单位和换算配置快照。
- 当前默认金额口径：`payment.cny_to_credit_rate = 1`，即 1 人民币 = 1 Sub2API 额度/刀；后续必须通过后台配置调整，不写死。

## 8. 后端任务清单

### B0：创建 Laravel 后端骨架

依赖：S0

交付：

- `backend/` Laravel 12 项目。
- `.env.example`。
- PostgreSQL、Redis 基础配置。
- Laravel Sanctum。
- 基础健康检查接口。

验收：

- `php artisan test` 可运行。
- `GET /api/v1/health` 返回统一响应。

### B1：模块基础结构

依赖：B0

交付：

- `app/Modules/*` 模块目录。
- 模块 ServiceProvider。
- 统一 API Response helper。
- 基础错误码。

验收：

- 模块可被 Laravel 自动加载。
- 新增模块目录已同步模块文档。

当前状态：

- 已新增 `backend/app/Modules/*` 与 `ModuleServiceProvider`。
- 已新增 `ApiError` 基础错误码，并在 API 异常中统一 JSON 响应。
- PHP/Composer 工具链已安装到项目 `.tools/conda/php/bin`。
- PHP 语法检查已通过；待 `composer install` 完整完成后执行自动加载、路由和测试验证。

### B2：Sub2API 用户适配与认证

依赖：B0、S1

交付：

- Sub2API 用户读取适配层。
- Sub2API 原生 affiliate 读取适配层：读取 `user_affiliates.aff_code` 并挂到用户资料。
- 普通用户登录接口。
- 管理员角色标记。
- Sanctum token 签发。
- Filament 管理员登录策略。

用户端 API：

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

验收：

- 普通用户只能访问用户 API。
- 管理员才能访问 Filament。
- 未登录返回 401 统一格式。

当前状态：

- 已实现 Sub2API `users` / `user_affiliates` 只读 Repository。
- 已实现账号密码登录、Sub2API bcrypt 校验、本地用户同步、Sanctum token 签发。
- 本地 `users` 快照已保存 Sub2API `aff_code` / `inviter_id`，方便用户资料和后续后台列表展示。
- 已实现 `GET /api/v1/auth/me` 和 `GET /api/v1/account/profile`。
- 资料接口已返回 Sub2Rebate 邀请链接和 Sub2API 原生 `aff_code` / 邀请链接。
- 已新增 `AuthAndProfileTest`，待 Composer 依赖完整安装后执行。
- Filament 管理员登录策略尚未实现，建议进入 B10 后台阶段处理。
- 待执行迁移、测试和真实 Sub2API 登录联调。

已确认：

- Sub2API 用户表为 `users`，登录身份辅助表为 `auth_identities`。
- `users.password_hash` 使用 bcrypt。
- Sub2Rebate 直接复用 Sub2API `users.id` 作为 canonical user id。
- Sub2API 原生邀请码在 `user_affiliates.aff_code`，只用于展示原生邀请链接。

### B3：Config 配置中心

依赖：B1

交付：

- `config_items` 迁移。
- 配置读取 Service。
- 默认配置 Seeder。
- Filament 配置页面。
- 配置修改审计事件。

第一版配置：

```text
milestone.amount = 100
milestone.reward_amount = 15
milestone.max_times = 2
rebate.pool_ratio = 0.15
rebate.mode = decay
rebate.decay_factor = 0.4
rebate.normalize = true
payment.cny_to_credit_rate = 1
withdraw.min_amount = 50
withdraw.review_mode = manual
withdraw.daily_limit = 1
withdraw.freeze_days = 0
risk.blacklist_enabled = true
rebate.inactive_node_mode = platform
```

验收：

- 配置读取不直接查询 DB。
- 修改配置写入 `audit_logs`。
- 配置页面参考 `sub2rebate_admin_1/screen.png`。

当前状态：

- 已实现 `config_items` 迁移。
- 已实现默认配置清单、`ConfigService` 和 `ConfigItemSeeder`。
- 已实现 `GET /api/v1/config/items`，返回配置项、Tips 和嵌套 `values`。
- 已新增 `ConfigItemsTest`。
- Filament 配置页面、配置修改审计和严格范围校验待 B10 后台阶段继续完成。

### B4：Invite 邀请关系

依赖：B2

交付：

- 邀请码生成。
- 邀请关系绑定。
- `referral_paths` 迁移，保存 `user_id`、`parent_user_id`、`path`、`depth`。
- 上级链路查询。
- 结构上级链路查询与返利可用上级链路查询分开。
- 下级树查询。
- 树节点返回返利资格/失效状态，供管理端置灰展示。
- 循环邀请检测。
- 用户树状层级接口，支持按 `maxDepth` 裁剪展示层级。

用户端 API：

```text
GET  /api/v1/invite/me
POST /api/v1/invite/bind
GET  /api/v1/invite/tree
GET  /api/v1/invite/records
```

验收：

- 用户只有一次绑定机会，管理员修正除外。
- A 邀请 B，B 邀请 C 后，C 的上级链路能查到 B 和 A。
- B 被判定为返利失效后，C 仍保留在 B 下级，`path` 和 `parent_user_id` 不自动改写。
- 管理端邀请树能区分 active 和 rebate_disabled 节点。
- 禁止自邀请和循环关系。

当前状态：

- 已实现 `InviteService`、`InviteController` 和 Invite 模块 Provider。
- 已实现 `GET /api/v1/invite/me`、`POST /api/v1/invite/bind`、`GET /api/v1/invite/tree`、`GET /api/v1/invite/records`。
- 已实现邀请码自动生成、一次绑定、自邀请/循环关系校验、A -> B -> C 上级链路查询。
- 已新增 `InviteTest`，覆盖绑定、树、记录和未登录拦截。
- 邀请记录中的充值金额和返利金额等待 B5/B6/B7 接入真实流水后补齐。

### B5：Payment 事件入口

依赖：B2、B3

交付：

- `payment_records`。
- `rebate_events`。
- 手动创建充值/后台补录事件的内部入口。
- 事件幂等唯一约束。
- 充值事件金额换算：保存来源金额、标准金额、单位和换算配置快照。
- 管理员手动补录充值事件入口，必须要求管理员权限、备注和审计。

后台能力：

- 管理员可模拟充值事件用于测试。
- Sub2API 外部充值/兑换事件后续通过同一个事件入口进入。
- 后续实际加额度应尽量从 Sub2Rebate 入口发起，避免绕过返利、权限和审计。

验收：

- 同一个 `source_type + source_id` 只能创建一次事件。
- 事件未处理、处理中、已处理、失败状态清晰。
- 普通用户不能创建或补录充值事件。
- 后台补录事件必须记录操作者、备注、来源和审计日志。

当前状态：

- 已实现 `payment_records` 迁移和模型。
- 已实现 `rebate_events` 迁移和模型，状态包含 `pending`、`processing`、`processed`、`failed`。
- 已实现 `RechargeEventService`，支持服务层创建充值事件和管理员手动补录。
- 已实现 `source_type + source_id` 幂等处理。
- 已保存原始金额、标准金额、Sub2API 额度金额和 `payment.cny_to_credit_rate` 配置快照。
- 已新增 `RechargeEventTest`。
- 后台补录 API/Filament Action、审计日志和 Sub2API 扫描器待 B10/B11 继续接入。

### B6：Milestone 里程碑奖励

依赖：B3、B4、B5

交付：

- `user_rebate_progress`。
- 里程碑判断 Action。
- 里程碑奖励发放 Action。
- 返利流水写入。
- 余额更新。

规则：

```text
新人累计充值每满 100 元触发一次。
每次直接上级获得 15 元。
默认最多触发 2 次。
里程碑阶段只奖励直接上级。
跨额充值按可触发次数处理，但不能超过 milestone.max_times。
```

验收：

- 充值 100 元，直接上级获得 15 元。
- 累计充值 200 元，直接上级共获得 30 元。
- 默认配置下单次充值 250 元最多触发 2 次里程碑，共发 30 元。
- 超过 2 次后不再走里程碑。
- 同一事件重复处理不重复发放。

当前状态：

- 已实现 `user_rebate_progress`、`rebate_records`、`rebate_balances` 迁移和模型。
- 已实现 `MilestoneService`，消费 B5 `rebate_events` 充值事件。
- 已实现直接上级奖励发放、返利余额增加、事件 processed 标记。
- 已实现跨额充值：单次 250 元触发 2 次，汇总写入 30 元里程碑流水。
- 已新增 `MilestoneTest`，覆盖 100、累计 200、单次 250、重复处理和无上级场景。
- B7 多级衰减返利服务层已完成；队列自动消费和后台展示待后续阶段继续。

### B7：Rebate 无限级衰减返利

依赖：B3、B4、B5、B6

交付：

- 衰减系数计算类。
- 无限级上级链路读取。
- 归一化分配。
- 尾差处理。
- 返利流水和余额更新。

规则：

```text
返利池 = 充值金额 * rebate.pool_ratio
第 N 级权重 = decay_factor ^ (N - 1)
有效上级按权重归一化分完整个返利池
尾差归入直接上级
失效节点不获得新返利
失效节点金额处理由 rebate.inactive_node_mode 控制
```

失效节点模式：

```text
platform:
    按原始层级计算，失效节点那一份不发，归平台保留。
    有效上级仍按原始层级权重获得返利。

exclude_recalculate:
    计算前排除失效节点。
    剩余有效上级按衰减系数重新编号、重新归一化。
```

验收：

- 3 级上级时，所有上级按 1、0.4、0.16 权重分配。
- 分配总额等于返利池。
- 直接上级金额最大。
- 没有上级时不发放，事件状态需清楚记录。
- A -> B -> C 中 B 返利失效时，B 不产生新的衰减返利流水。
- `platform` 模式下，B 对应金额归平台，A 仍按原始第 2 级权重计算。
- `exclude_recalculate` 模式下，排除 B 后对 A 等有效上级重新按衰减系数计算。

当前状态：

- 已实现 `DecayRebateCalculator`。
- 已实现 `DecayRebateService`，消费 B5 `rebate_events` 正常返利部分。
- 已实现里程碑结束判断：未超过阈值不发衰减返利。
- 已实现跨额处理：首充 250 元时，里程碑覆盖 200 元，衰减返利只计算剩余 50 元。
- 已实现无限级上级链路读取、归一化分配、尾差归直接上级、返利流水和余额更新。
- 已新增 `DecayRebateTest`，覆盖 3 级分配、总额守恒、直接上级最大、幂等、无上级和 250 元跨额场景。
- 自动队列消费和页面查询待后续阶段接入。

### B8：Balance 与 Withdraw

依赖：B6、B7

交付：

- `rebate_balances`。
- `withdraw_accounts`。
- `withdraw_records`。
- 用户提现申请。
- 余额冻结、解冻、扣减。
- 管理员审核、拒绝、标记打款。
- 提现配置由后台可调，包括最低提现金额、每日次数、冻结天数和审核模式。

用户端 API：

```text
GET  /api/v1/withdraw/config
GET  /api/v1/withdraw/account
POST /api/v1/withdraw/account
POST /api/v1/withdraw/apply
GET  /api/v1/withdraw/records
```

验收：

- 默认最低提现金额 50 元，可由管理员调整。
- 余额不足不能提现。
- 黑名单或冻结用户不能提现。
- 提交提现后可提现余额减少，冻结金额增加。
- 拒绝后解冻。
- 标记已打款后扣减冻结金额。

当前状态：

- 已实现 `withdraw_accounts` 和 `withdraw_records` 迁移与模型。
- 已实现 `WithdrawService`，支持提现配置、提现账号保存/读取、提现申请和记录分页。
- 已实现用户端 API：`GET /withdraw/config`、`GET/POST /withdraw/account`、`POST /withdraw/apply`、`GET /withdraw/records`。
- 已实现申请提现时可用余额减少、冻结余额增加。
- 已实现默认最低提现 50 元、每日 1 次、账号必填、余额不足校验。
- 已新增 `WithdrawTest`。
- 管理员审核、拒绝解冻、标记打款、审计日志和黑名单限制待 B9/B10 继续。

### B9：Audit 与 Risk

依赖：B3、B8

交付：

- `audit_logs`。
- `risk_flags`。
- 审计记录 Service。
- 黑名单和提现冻结。
- 返利资格变更审计。
- 失效节点判定和恢复记录。
- 管理后台审计列表和详情。

验收：

- 返利发放、提现申请、提现审核、配置修改、余额手工调整都写审计。
- 审计页面参考 `sub2rebate_admin_2/screen.png` 和 `sub2rebate_admin_3/screen.png`。
- 风控状态能阻止提现。
- 用户被标记为返利失效时不影响登录，除非账号状态被明确封禁。
- 返利资格恢复后，后续新事件按恢复后的资格参与计算，历史流水不重算。

当前状态：

- 已实现 `audit_logs` 迁移、模型和 `AuditLogService`。
- 已实现 `risk_flags` 迁移、模型和 `RiskService`。
- 已接入提现申请审计。
- 已接入里程碑返利和多级衰减返利发放审计。
- 已接入黑名单/提现冻结阻止提现。
- 已新增 `AuditRiskTest`。
- 提现审核、配置修改、余额手工调整、邀请关系修正等后台操作审计待 B10 接入。
- 审计列表/详情页面和风控管理页面待 B10 Filament 后台实现。

### B10：Filament 管理后台

依赖：B2-B9

交付页面：

- 后台总览。
- 用户列表和用户详情。
- 邀请关系和邀请树。
- 返利流水。
- 余额管理和手工调整。
- 提现审核。
- 配置中心。
- 风控名单。
- 审计日志。

效果稿映射：

| 后台页面 | 效果稿 |
|---|---|
| 后台总览 | `admin_dashboard_sub2rebate/screen.png` |
| 配置中心 | `sub2rebate_admin_1/screen.png` |
| 审计日志 | `sub2rebate_admin_2/screen.png` |
| 审计详情 | `sub2rebate_admin_3/screen.png` |
| 用户余额调整 | `sub2rebate_admin_4/screen.png` |
| 提现审核 | `sub2rebate_admin_5/screen.png` |
| 用户详情 | `sub2rebate_admin_6/screen.png` |
| 邀请树 | `referral_visualization_sub2rebate_admin/screen.png` |

验收：

- 所有敏感操作需要备注。
- 所有敏感操作写入审计。
- 提现审核状态流转不可跳状态。
- 后台登录优先依据 Sub2API `users.role = admin`，并允许 `sub2_user_roles` 做本系统补充限制。
- 普通 Sub2API 用户不能访问 Filament 后台或管理接口。

当前状态：

- 已确认 Filament 3.3.54 已安装可用。
- 已实现 `AdminPanelProvider`，后台入口为 `/admin`。
- 已实现自定义 Filament 登录页，复用 Sub2API bcrypt 登录适配，同步本地用户但不签发用户端 Sanctum token。
- 已实现 `User::canAccessPanel()`，普通用户不能访问后台。
- 已实现 `AdminAccessService`，当前基于本地同步 `users.role = admin` 和 `status=active` 判断管理员。
- 已实现 `AdminWithdrawService`，支持提现审核通过、拒绝解冻、标记打款。
- 后台提现审核动作已要求备注、限制状态流转，并写入审计日志。
- 已新增 `AdminWithdrawTest`。
- 已新增 `FilamentAdminTest`，覆盖后台登录页、未登录跳转和后台访问权限。
- 已实现用户、配置、提现审核、返利事件、返利流水、返利余额、里程碑进度、风控标记、审计日志和 Sub2API 上游账号监控 Resource。
- 已实现配置修改备注和审计、提现审核页面动作、上游账号手动同步入口。
- 待继续实现：邀请关系可视化、用户余额调整弹窗、充值事件补录入口、完整后台首页统计。

### B11：Sub2API 集成

依赖：B2、B5、B7

交付：

- Sub2API 表结构读取说明。
- 充值、兑换、支付审计扫描方案。
- Sub2API 外部事件转换为 `rebate_events`。
- 防重复处理。

优先方案：

```text
共享 PostgreSQL 只读连接
-> 扫描 payment_orders / redeem_codes / payment_audit_logs
-> 写入 rebate_events
-> 队列处理返利
```

验收：

- Sub2API 兑换码、支付订单、支付审计可转换为幂等 `rebate_events`。
- Sub2API 管理员手动加额度如需返利，原则上统一走兑换码/订单/Sub2Rebate 后台补录入口。
- `users.balance` / `users.total_recharged` 只做快照对账和异常提示，不能直接自动触发返利。
- 同一外部事件不会重复发放。

待确认：

- 生产库表结构是否与当前源码迁移一致。
- 管理员手动调额是否强制统一改走兑换码/订单/Sub2Rebate 后台入口；如果继续直接在 Sub2API 改 `users.balance`，只能做人工确认，不自动发放返利。

### B12：Sub2API 上游账号监控

依赖：B2、B10

定位：

`admin/accounts` 是 Sub2API 的上游模型账号/渠道账号，不是用户账号。第一版必须做运营监控，但它不参与返利发放和用户账务计算。

交付：

- Sub2API Admin API 配置：`SUB2API_BASE_URL`、`SUB2API_ADMIN_API_KEY`。
- Admin API client，使用 `x-api-key` 调用 Sub2API。
- 上游账号列表适配：`GET /api/v1/admin/accounts`。
- 上游账号详情适配：`GET /api/v1/admin/accounts/{id}`。
- 上游账号用量适配：`GET /api/v1/admin/accounts/{id}/usage`。
- 上游账号统计适配：`GET /api/v1/admin/accounts/{id}/stats`。
- 今日统计适配：`GET /api/v1/admin/accounts/{id}/today-stats`。
- Filament 上游账号监控页面，展示账号状态、额度/用量、近期统计和更新时间。
- 手动刷新入口和失败日志。

验收：

- 管理员可在 Sub2Rebate 后台查看 Sub2API 上游账号列表。
- 管理员可进入单个上游账号查看用量、统计和今日数据。
- 调用 Sub2API Admin API 必须使用 `x-api-key`，不能使用后台网页登录密码做自动化凭据。
- 页面文案明确 `accounts` 是上游模型账号/渠道账号，不是用户。
- Admin API 调用失败时只影响监控页面，不能阻塞登录、返利、提现等主流程。
- 不把上游账号数据写入返利事件源，不用它计算用户返利。

当前状态：

- 已新增 `SUB2API_BASE_URL`、`SUB2API_ADMIN_API_KEY`、`SUB2API_ADMIN_TIMEOUT` 配置。
- 已实现 `Sub2ApiAdminClient`，所有 Admin API 调用使用 `x-api-key`。
- 已新增 `sub2api_upstream_accounts` 本地监控快照表和 `Sub2ApiUpstreamAccount` 模型。
- 已实现 `Sub2ApiUpstreamAccountSyncService`，支持同步上游账号列表、详情、用量、统计、今日统计；失败时记录最近错误并写入审计日志。
- 已实现 Filament `Sub2ApiUpstreamAccountResource`，支持列表、详情、手动同步和原始数据查看。
- 已新增 `Sub2ApiUpstreamAccountTest`，覆盖 `x-api-key`、同步快照、详情数据、失败审计和不写入 `rebate_events`。

## 9. 前端架构目录规则

前端使用 Vue 3 + Vite + TypeScript + Element Plus + Pinia + Vue Router + Axios。

推荐目录：

```text
frontend/
├── public/
├── src/
│   ├── api/
│   │   ├── auth.ts
│   │   ├── dashboard.ts
│   │   ├── promotion.ts
│   │   ├── rebate.ts
│   │   ├── withdraw.ts
│   │   └── account.ts
│   ├── assets/
│   ├── components/
│   │   ├── charts/
│   │   ├── common/
│   │   ├── data/
│   │   └── layout/
│   ├── composables/
│   ├── layouts/
│   │   ├── UserLayout.vue
│   │   └── AuthLayout.vue
│   ├── mocks/
│   ├── router/
│   │   ├── index.ts
│   │   └── routes.ts
│   ├── stores/
│   │   ├── auth.ts
│   │   ├── dashboard.ts
│   │   ├── promotion.ts
│   │   └── withdraw.ts
│   ├── styles/
│   │   ├── tokens.css
│   │   ├── element-plus.css
│   │   └── index.css
│   ├── types/
│   │   ├── api.ts
│   │   ├── rebate.ts
│   │   ├── withdraw.ts
│   │   └── user.ts
│   ├── utils/
│   │   ├── money.ts
│   │   ├── request.ts
│   │   └── status.ts
│   └── views/
│       ├── auth/
│       ├── dashboard/
│       ├── promotion/
│       ├── withdraw/
│       └── account/
├── package.json
├── vite.config.ts
└── tsconfig.json
```

前端规则：

- 页面只做展示和用户交互，关键金额不在前端计算。
- API 请求统一走 `src/utils/request.ts`。
- 金额展示统一走 `src/utils/money.ts`。
- 状态文案和颜色统一走 `src/utils/status.ts`。
- 页面数据优先通过 Pinia store 管理。
- 大图表用 ECharts。
- 邀请树可视化用 AntV G6。
- Element Plus 用于表单、表格、弹窗、分页、日期选择。
- 布局和视觉按效果稿，还原时统一中文文案。

## 10. 前端任务清单

### F0：创建 Vue 前端骨架

依赖：S0

交付：

- `frontend/` Vite + Vue 3 + TypeScript。
- Element Plus。
- Pinia。
- Vue Router。
- Axios。
- Tailwind CSS。
- 基础路由。

验收：

- `npm run dev` 可启动。
- `npm run build` 可通过。

### F1：设计 Token 和基础布局

依赖：F0

交付：

- `tokens.css`。
- Element Plus 主题覆盖。
- 用户端左侧导航。
- 顶部栏。
- 响应式主内容容器。
- 通用卡片、指标卡、状态标签、空状态。

视觉依据：

- `precision_rebate_intelligence/DESIGN.md`
- `user_dashboard_sub2rebate/screen.png`

验收：

- 用户端基础布局与效果稿一致。
- 移动端不出现文字重叠。

### F2：API 层和 Mock 层

依赖：S1、S2、F0

交付：

- Axios 实例。
- token 注入。
- 401 处理。
- API 类型定义。
- Mock 数据开关。

验收：

- 后端未完成时，用户端所有页面可以用 Mock 数据跑通。
- API 类型与 `docs/API_CONTRACT.md` 一致。

### F3：登录和会话

依赖：F2

交付：

- 登录页。
- 登录状态持久化。
- 路由守卫。
- 退出登录。
- 当前用户信息展示。

验收：

- 未登录访问业务页跳登录。
- 登录后进入仪表盘。
- 退出后清除 token。

### F4：用户仪表盘

依赖：F1、F2

路由：

```text
/dashboard
```

交付：

- 可提现余额。
- 冻结金额。
- 累计返利。
- 邀请总数。
- 返利趋势图。
- 最近返利记录。

效果稿：

- `user_dashboard_sub2rebate/screen.png`
- `dashboard_overview_sub2rebate_user/screen.png`
- `dashboard_overview_animated_sub2rebate_user/screen.png`

验收：

- 指标、图表、表格能展示真实或 Mock 数据。
- 表格状态中文化。
- 关键按钮能跳转提现和推广中心。

### F5：推广中心

依赖：F1、F2

路由：

```text
/promotion
/promotion/invites
/promotion/rebates
```

交付：

- 推广链接。
- 邀请码。
- 复制链接。
- 邀请统计。
- 多级返利说明。
- 最近转化记录。
- 营销素材入口。

效果稿：

- `promotion_center_sub2rebate_user/screen.png`
- `promotion_center_sub2rebate/screen.png`

验收：

- 复制链接使用成熟工具或浏览器 API 封装。
- 统计卡片和记录列表对齐效果稿。
- 邀请层级展示不参与金额计算。

### F6：提现管理

依赖：F1、F2

路由：

```text
/withdraw
/withdraw/records
```

交付：

- 可提现余额展示。
- 提现金额输入。
- 支付宝账号绑定。
- 提现提交。
- 提现记录。
- 提交成功弹窗。

效果稿：

- `withdraw_management_interactive_sub2rebate_user/screen.png`
- `withdraw_management_sub2rebate/screen.png`

验收：

- 前端做基础必填和格式提示。
- 后端业务校验失败时展示错误消息。
- 状态包括待审核、已通过、已打款、已拒绝、失败、已取消。

### F7：账户设置

依赖：F1、F2

路由：

```text
/account
```

交付：

- 个人信息展示。
- 安全状态展示。
- API Key 管理先做展示或后续入口。
- 隐私/数据区域。

效果稿：

- `account_settings_sub2rebate_user/screen.png`

验收：

- 第一版不做复杂安全功能时，页面明确只展示可用信息。
- 不出现无后端支撑的假操作。

### F8：前端联调和真实 API 切换

依赖：F3-F7、B2-B8

交付：

- 关闭 Mock。
- 接入真实 API。
- 统一错误提示。
- 加载态、空状态、失败态。

验收：

- 登录、仪表盘、推广、提现全流程走真实 API。
- API 字段变更必须先更新契约文档。

## 11. UI 效果稿实施映射

| 效果稿目录 | 实施位置 | 优先级 | 备注 |
|---|---|---:|---|
| `user_dashboard_sub2rebate` | 前端 `/dashboard` | P0 | 用户首页主参考 |
| `dashboard_overview_sub2rebate_user` | 前端 `/dashboard` | P1 | 指标和历史记录参考 |
| `dashboard_overview_animated_sub2rebate_user` | 前端 `/dashboard` | P2 | 动效后置 |
| `promotion_center_sub2rebate_user` | 前端 `/promotion` | P0 | 推广中心主参考 |
| `promotion_center_sub2rebate` | 前端 `/promotion` | P1 | PC 布局补充参考 |
| `withdraw_management_interactive_sub2rebate_user` | 前端 `/withdraw` | P0 | 提现主参考 |
| `withdraw_management_sub2rebate` | 前端 `/withdraw` | P1 | 表单细节补充 |
| `account_settings_sub2rebate_user` | 前端 `/account` | P1 | 账户设置 |
| `admin_dashboard_sub2rebate` | Filament 后台首页 | P1 | 管理概览 |
| `referral_visualization_sub2rebate_admin` | Filament 自定义邀请树页 | P1 | 可用 G6 或后台自定义页 |
| `sub2rebate_admin_1` | 配置中心 | P0 | MVP 必做 |
| `sub2rebate_admin_2` | 审计日志 | P0 | MVP 必做 |
| `sub2rebate_admin_3` | 审计详情 | P1 | 可随审计页完成 |
| `sub2rebate_admin_4` | 用户余额调整 | P1 | 敏感操作必须审计 |
| `sub2rebate_admin_5` | 提现审核 | P0 | MVP 必做 |
| `sub2rebate_admin_6` | 用户详情 | P1 | 用户画像和流水 |

## 12. 联调任务

### I0：联调前检查

检查项：

- 后端迁移可执行。
- 后端 Seeder 可生成测试配置。
- 前端 Mock 可关闭。
- API 契约与后端响应一致。
- 测试用户和管理员账号可用。

### I1：核心业务场景

测试链路：

```text
管理员准备配置
-> 用户 R 登录并获取邀请码
-> 用户 A 绑定 R 的邀请码
-> 用户 B 绑定 A 的邀请码
-> B 产生第一笔 100 元充值事件
-> A 获得 15 元里程碑奖励
-> B 产生第二笔 100 元充值事件
-> A 再获得 15 元里程碑奖励
-> B 产生第三笔充值事件
-> A 和 R 按衰减系数获得多级返利
-> A 申请提现
-> 管理员审核通过并标记打款
-> 审计日志可完整追踪
```

### I2：异常业务场景

必须覆盖：

- 重复充值事件不会重复发放。
- 没有上级的用户充值不产生返利。
- 黑名单用户不能提现。
- 余额不足不能提现。
- 提现拒绝后余额解冻。
- 配置修改写入审计。
- 管理员手工调整余额写入审计。

## 13. 测试计划

后端测试：

| 类型 | 覆盖 |
|---|---|
| Unit | 里程碑计算、衰减返利、尾差、余额冻结 |
| Feature | 登录、绑定邀请、提现申请、配置修改 |
| 幂等 | 重复事件、重复提现状态操作 |
| 权限 | 普通用户和管理员隔离 |

前端测试：

| 类型 | 覆盖 |
|---|---|
| Vitest | money/status/request 工具函数 |
| 组件测试 | 指标卡、状态标签、提现表单 |
| 手动视觉检查 | 效果稿关键页面对齐、移动端不重叠 |
| 构建检查 | `npm run build` |

最终验收命令建议：

```text
cd backend && php artisan test
cd frontend && npm run build
```

## 14. 部署计划

第一版部署形态：

```text
Nginx
├── /                 Vue 前端静态资源
├── /api/v1/          Laravel API
└── /admin/           Filament 管理后台

PostgreSQL            主数据库
Redis                 缓存和队列
Laravel Queue Worker  返利事件处理
Laravel Scheduler     Sub2API 额度监控
```

Docker 交付：

- `docker-compose.yml`
- `docker/nginx/default.conf`
- 后端 Dockerfile。
- 前端 Dockerfile 或构建产物挂载方案。
- `.env.example`。

上线前检查：

- 生产环境配置不使用测试密钥。
- 管理员账号已设置。
- 队列 Worker 已启动。
- Scheduler 已配置。
- 数据库备份策略已明确。
- 支付宝自动化未启用时，提现页面和后台文案必须说明人工审核。

## 15. 失败重启规则

如果中途失败，按当前任务线重新开始，不靠猜测补代码：

1. 先看失败命令输出、Laravel 日志、浏览器控制台或网络请求。
2. 如果信息不足，先加最小必要日志，让用户提供输出。
3. 回到当前任务编号的入口重新执行，例如 B7 失败就从 B7 的输入、配置、测试用例重跑。
4. 不把一个失败点扩散成大量兼容异常数据的防御判断。
5. 不改无关模块。
6. 修复后补测试和文档记录。

每个阶段的检查点：

- 代码可运行。
- 测试或手动验证有记录。
- 文档已更新。
- 未完成项明确写入模块文档。

## 16. 待确认项

这些问题不阻塞骨架和大部分业务开发，但上线前必须确认：

| 问题 | 影响 | 临时处理 |
|---|---|---|
| 返利冻结天数是否后续启用 | 余额可提现口径 | 第一版默认 0 天，即返利确认后直接可提现 |
| 管理员直接调余额的来源识别 | 手动加额度返利 | 暂不禁止直接在 Sub2API 改余额；这类变更只做对账提示和人工确认，不自动返利 |

## 17. 推荐执行顺序

第一批：

```text
S1 API 契约
B0 后端骨架
F0 前端骨架
```

第二批：

```text
B1 模块基础
B2 认证
B3 配置
F1 基础布局
F2 API/Mock
F3 登录
```

第三批：

```text
B4 邀请
B5 事件入口
B6 里程碑
B7 多级返利
F4 仪表盘
F5 推广中心
```

第四批：

```text
B8 提现
B9 审计风控
B10 Filament 后台
F6 提现
F7 账户设置
```

第五批：

```text
B11 Sub2API 集成
B12 Sub2API 上游账号监控
F8 真实 API 切换
I0-I2 联调验收
部署检查
```

## 18. 完成标准

当以下条件全部满足，第一版才算完成：

- 用户能登录。
- 用户能获取推广链接并绑定邀请关系。
- 邀请树和上级链路正确。
- 里程碑奖励发放正确。
- 多级衰减返利发放正确。
- 余额正确累计、冻结、解冻、扣减。
- 用户能提现。
- 管理员能审核提现。
- 配置中心可修改关键参数。
- 管理员能查看 Sub2API 上游账号状态、用量和统计。
- 审计日志可追踪关键行为。
- 用户端页面完成效果稿 P0/P1。
- 后台页面完成效果稿 P0/P1。
- 后端核心测试通过。
- 前端构建通过。
- 文档、日志、项目状态已更新。

## 19. 补充功能需求（2026-06-13 追加）

以下需求需在对应阶段中实现，后端需根据此部分核对接口设计。

### 19.1 余额调整与 API 额度管理充值功能合并

**问题**：`AdminBalanceAdjustView`（余额调整独立页面）与 `AdminApiQuotaView`（API 额度管理充值）功能重叠，两者都涉及"给用户增加/减少额度"，容易混淆。

**决策**：
- 废弃独立的余额调整页面（`AdminBalanceAdjustView`）。
- 余额调整功能作为用户管理页面（`AdminUsersView`）的弹窗使用（当前已有此弹窗实现）。
- `AdminApiQuotaView` 保留，作为管理员给用户充值 API 额度的专用功能（对应 Sub2API 的额度体系）。
- 明确区分："余额调整"操作的是返利余额（Sub2Rebate 独立余额），"API 额度充值"操作的是 Sub2API 的 API 调用额度。

**前端任务**：
- 移除路由中 `AdminBalanceAdjustView` 的独立入口。
- 确认 `AdminUsersView` 弹窗功能完整（已有）。
- `AdminApiQuotaView` 页面标题和说明文案明确标注为"API 调用额度"。

**后端接口**：
- `POST /admin/users/{id}/balance-adjust`：调整返利余额（已有）。
- `POST /admin/users/{id}/api-quota`：充值 API 额度（调用 Sub2API，需触发返利事件）。
- 两个接口语义独立，参数和审计日志分开记录。

---

### 19.2 推荐关系页面交互增强（管理后台）

**页面**：`AdminRelationshipView`

**当前状态**：已有画布缩放、搜索用户、节点展开/折叠、节点详情弹窗。

**补充需求**：

| 功能 | 说明 | 优先级 |
|---|---|---|
| 移动画布 | 鼠标拖拽平移画布（当前已有 isDragging 基础实现，确认完善） | P0 |
| 查看用户信息 | 点击节点弹窗展示用户详细信息（昵称、充值、返利余额、下级数等） | P0 |
| 搜索指定用户 | 搜索后以该用户为根节点展示其完整推荐关系树 | P0 |
| 大层级优化 | 层级深时的性能优化：虚拟滚动、懒加载子树（按需请求）、折叠超过N层的节点 | P1 |
| 全屏模式 | 画布支持全屏展示 | P2 |
| 缩略导航 | 右下角小地图显示当前视野位置 | P2 |

**后端接口**：
- `GET /admin/relationship/tree?userId={id}&depth={n}`：支持 `depth` 参数控制返回层级深度（懒加载用）。
- `GET /admin/relationship/children?userId={id}`：获取某节点直接子节点（按需加载用）。
- `GET /admin/users/{id}/summary`：用户摘要信息（节点弹窗用）。

---

### 19.3 推广用户端推荐关系页面

**页面**：`MyRelationshipView`（推广用户/推广员端）

**核心规则**：
- 只能查看自己作为根节点的下级关系树。
- 上级关系完全不可见（不返回、不展示）。
- 展示自己的直接下级和间接下级。

**交互需求**（同管理后台）：
- 移动画布。
- 点击节点查看下级用户基础信息（仅限：昵称、加入时间、状态，不展示充值金额等敏感数据）。
- 大层级优化同上。

**后端接口**：
- `GET /promotion/my-relationship?depth={n}`：返回当前登录用户的下级关系树。
- 后端强制以当前用户为根节点，不接受 userId 参数（防越权）。
- 返回数据中不包含上级任何信息。
- 下级用户信息脱敏：不返回充值金额、返利余额等财务字段。

---

### 19.4 推广员扫码支付增加额度

**页面**：`RechargeView`（充值页面，用户端）

**当前状态**：已有充值套餐选择、支付宝支付流程 UI、订单轮询逻辑。

**补充需求（完整闭环）**：

流程：
```text
推广员选择充值金额
-> 创建支付订单
-> 生成支付宝扫码链接/二维码
-> 用户扫码支付
-> 支付宝回调通知
-> 后端验签确认支付成功
-> 增加用户 API 额度
-> 触发返利事件（给上级链路发放返利）
-> 前端轮询订单状态 -> 展示支付成功
```

**后端接口**：
- `POST /recharge/create-order`：创建充值订单，返回支付宝支付链接。
- `POST /recharge/alipay-notify`：支付宝异步回调（验签、幂等处理、增加额度、触发返利）。
- `GET /recharge/order-status/{orderId}`：前端轮询订单状态。
- `GET /recharge/records`：充值记录列表。

**关键逻辑**：
- 支付回调必须验签（支付宝公钥验证）。
- 幂等处理：同一 `trade_no` 不重复增加额度。
- 支付成功后调用返利引擎，按推荐关系给上级发放返利。
- 订单状态机：`pending -> paid -> completed`（或 `failed / expired`）。

---

### 19.5 返利配置中心 Tooltip 说明

**页面**：`AdminRebateConfigView`

**需求**：每个配置项旁增加 info 图标，鼠标悬停（hover）时展示 Tooltip 说明文案。

**说明内容参考**：根目录 `方案研讨.md` 第 26 节。

**具体 Tooltip 文案**：

| 配置项 | Tooltip 说明 |
|---|---|
| 里程碑金额 | 新用户累计充值达到此金额时触发里程碑奖励。例如设为 100，表示每累计充值 100 元触发一次。 |
| 每次奖励金额 | 每次触发里程碑时奖励给直接上级的金额。该金额从返利池中扣除。 |
| 最大奖励次数 | 每个新用户最多触发的里程碑奖励次数。达到次数后进入正常多级返利模式。 |
| 返利池比例 | 每笔充值中用于返利分发的比例。例如 15% 表示用户充值 100 元，其中 15 元进入返利池分给上级链路。调大增加推广激励但提高平台成本。 |
| 衰减系数 | 多级返利中每向上一层的衰减比例。例如 0.5 表示第2级拿第1级的一半。系数越小，高层级分到越少；系数越大，分配越均匀。 |
| 最大分发层级 | 返利最多向上分发多少层。超过此层级的上级不参与本次返利分发。 |
| 失效节点返利处理方式 | 归平台：失效节点对应返利不再发放，也不会转给其他上级，该部分金额归平台保留，树结构和原始层级不变。排除后重算：计算层级返利时跳过失效节点，并按剩余有效上级重新计算衰减权重，上级可能获得比原始层级更高的返利。 |
| 最低提现金额 | 用户申请提现时余额必须达到的最低金额。防止频繁小额提现增加运营成本。 |
| 提现冷却时间 | 两次提现申请之间的最短间隔（小时）。防止频繁操作。 |
| 黑名单启用 | 开启后，被加入黑名单的用户无法获得返利，其下级充值也不触发向上返利。 |
| 自动冻结阈值 | 单日内同一上级获得的返利笔数超过此值时自动冻结待人工审核。用于防刷单。 |

**前端实现**：
- 使用 Element Plus 的 `<el-tooltip>` 组件。
- 在每个配置项 label 后放置 info 图标（`InfoFilled`）。
- hover 时显示 Tooltip，placement 建议 `top`。

**后端无需额外接口**，Tooltip 文案写在前端。
