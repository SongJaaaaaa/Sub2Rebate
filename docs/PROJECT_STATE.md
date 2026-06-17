# 当前项目状态

> 这个文件用于快速恢复上下文。每次开发完成后必须更新。

## 1. 当前阶段

阶段 1：基础骨架已启动，B1/B2 核心后端适配已开始落地。

第一批实际开发任务已完成：S1 API 契约、B0 后端骨架、F0 前端骨架已落地。B1 模块基础、B2 Sub2API 用户读取/登录/资料聚合已有首版代码。

2026-06-13 已再次复核 S1/B0/B1/B2/F0：API 契约、认证接口、Sanctum token、前端路由/请求/状态骨架与 Mock 数据均已存在；后端测试和前端构建已通过。

2026-06-13 完成当前 goal 审计：S1/B0/B1/B2/F0 均有当前文件和命令结果证明完成，Sub2API 集成边界仍保持为只读用户/充值事件扫描、上游账号运营监控、余额快照不自动触发返利。

2026-06-13 第一轮实施完成：补齐 Admin API 路由组（8 个 Controller、20+ 接口）、返利事件队列消费（ProcessRebateEventJob + rebate:process-pending）、用户端仪表板/返利记录/推广统计接口、ConfigService 批量写入能力、EnsureAdmin 中间件。测试通过：59 tests / 389 assertions。前端可从 Mock 切换到真实后端。

2026-06-14 接口文档规则已收口：`docs/API_CONTRACT.md` 明确为前后端联调唯一接口口径，后续新增、修改、删除接口必须同步更新；当前 `/api/v1` 已实现 41 条路由已补充接口索引，Admin API 详情和用户端已实现接口示例已同步。

2026-06-14 真实 Sub2API 账号登录联调已通过：管理员账号和普通用户账号均可作为 Sub2Rebate 登录账号使用。后端仍按 Sub2API `users.password` 哈希校验用户输入密码，登录成功后同步本地用户并签发 Sub2Rebate 自己的 Sanctum token；明文测试密码不进入代码、文档或日志。

2026-06-14 修复真实数据管理端异常：补跑本地迁移解决 `audit_logs` 缺表导致的余额记录 500；管理用户接口补齐前端展示字段；余额调整记录、推荐关系树、用户返利覆盖配置已适配真实 API 数据形态；推荐关系树节点补齐 `avatar`、`level`、`totalRecharge`、`directReferrals`、`status`；前端管理页统一使用 `userName/userAccount/userInitial` 处理 Sub2API 空用户名/昵称。

2026-06-14 Sub2API 真实邀请链路生产验证已完成：临时打开注册后使用普通用户 ID 2 的 Sub2API 邀请码创建测试账号 ID 3，生产只读库确认 `user_affiliates.inviter_id = 2`；随后已恢复 `registration_enabled = false`。本地 Sub2Rebate 同步后 `referral_paths` 为 `parent_user_id = 2`、`path = 2/3`、`depth = 1`，邀请树和邀请记录均可看到测试用户 3。

2026-06-14 完成安全与并发审计修复：返利余额入账改数据库原子更新；后台调额、提现申请/审核余额变更加行锁；返利事件状态由 Job 统一最终标记；登录和提现申请增加限流；封禁用户会撤销 Sanctum token，token 默认 7 天过期；本地改密接口改为提示 Sub2API 统一管理；Sub2API 只读连接故障时邀请/资料接口降级使用本地数据；新增 `referral_paths.path` PostgreSQL 前缀索引、CORS 配置、后台配置基础范围校验和审计 IP/User-Agent 默认记录。

2026-06-15 提交前进度已收口：当前工作区包含 Laravel 12 后端骨架、Vue 3 前端骨架、Admin/用户端真实 API 联调、安全并发修复、Sub2API 真实登录和邀请链路验证、44 条 `/api/v1` 接口文档，以及模块/决策/开发日志同步更新。下一步继续 B11 Sub2API 充值事件扫描器和 B10 复杂后台页面。

B3 配置中心后端读取基础已落地：`config_items`、默认配置、`ConfigService` 和 `GET /api/v1/config/items` 已可用；Filament 配置修改和审计后续在 B10 后台阶段实现。

B4 邀请关系后端基础已落地：邀请码、绑定、上级链路、邀请树和邀请记录接口已可用；邀请记录里的充值/返利金额等待查询逻辑接入真实流水后补齐。

B5 充值事件服务层入口已落地：`payment_records`、`rebate_events`、`RechargeEventService` 和幂等写入已可用；后台补录页面/API、审计日志和 Sub2API 扫描器后续接 B10/B11。

B6 里程碑奖励服务层已落地：`user_rebate_progress`、`rebate_records`、`rebate_balances`、`MilestoneService` 和返利余额更新已可用；队列自动消费和后台展示后续接入。

B7 多级衰减返利服务层已落地：`DecayRebateCalculator`、`DecayRebateService`、归一化分配、尾差处理、正常返利流水和余额更新已可用；自动队列消费和页面查询后续接入。

B8 用户提现申请基础已落地：提现配置、提现账号、提现申请、余额冻结和提现记录接口已可用；管理员审核、拒绝解冻、标记打款和审计日志后续接 B9/B10。

B9 审计风控基础已落地：`audit_logs`、`risk_flags`、`AuditLogService`、`RiskService` 已可用；提现申请、里程碑返利、多级衰减返利已写审计，黑名单/提现冻结可阻止提现。

B10 后台入口、核心动作和基础 Resource 已落地：Filament 3.3.54 已确认可用，`AdminPanelProvider`、自定义后台登录页、`User::canAccessPanel()`、`AdminAccessService` 和 `AdminWithdrawService` 已实现；用户、配置、提现审核、返利事件、返利流水、返利余额、里程碑进度、风控标记、审计日志、上游账号监控等 Resource 已可访问；提现审核和配置修改已要求备注并写审计。

B12 Sub2API 上游账号监控首版已落地：已新增 Admin API client、`x-api-key` 鉴权配置、本地上游账号快照表、手动同步列表/详情入口、失败审计日志和 Feature Test；该模块只做运营监控，不参与返利事件、支付记录或用户账务计算。

Sub2API 集成调研已完成源码、公网入口和生产库只读核验：用户账号共用、共享数据库、Admin API 运营监控均可行。

## 2. 已确定技术栈

```text
后端：Laravel 12
管理后台：Filament 3
用户前端：Vue 3 + Vite
UI 框架：Element Plus
状态管理：Pinia
路由：Vue Router
请求库：Axios
数据库：PostgreSQL
缓存/队列：Redis
部署：Docker + Nginx
```

## 3. 已确定第一版目标

第一版完成：

- 邀请关系（无限级金字塔）。
- 邀请树。
- 新人累计充值里程碑奖励（默认 2 次）。
- 衰减系数多级返利（无限级）。
- 独立返利余额。
- 人工提现。
- 管理后台配置。
- 完整流水。

## 4. 第一版暂不实现

- 支付宝自动充值。
- 支付宝自动提现。
- 贡献系数自动奖惩（只记录数据）。
- 活动多规则并行。
- 推广员等级。

## 5. 已确认决策

- 管理员在 Sub2API 手动加额度：必须进入明确事件入口后才触发返利，优先改走兑换码/订单/Sub2Rebate 后台补录入口；直接调余额只做对账和人工确认。
- 涉及返利的充值/加额度必须进入 Sub2Rebate 的幂等事件入口；`users.balance` 会随消费减少，只能做对账和人工确认，不能直接自动发放返利。
- Sub2Rebate 自身充值/加额度：触发返利。
- 用户认证：复用 Sub2API 用户表，Sub2Rebate 签发自己的 token，区分管理员和普通用户。
- 登录密码校验：由后端拿用户输入密码和 Sub2API 用户表中的密码哈希做校验；不需要、也不应该把真实明文密码写入配置或文档。
- 里程碑奖励次数：默认 2 次，可后台配置；默认 100 元一个里程碑，单次充值 250 元最多触发 2 次。
- 多级分发模式：衰减系数模式（默认衰减系数 0.4，可配置），无限级金字塔结构。
- 返利池比例：默认 15%，可配置。
- 金额换算：当前默认 1 人民币 = 1 Sub2API 额度/刀，但必须通过后台配置维护，不能写死。
- Sub2API 集成方式：共享数据库只读读取 `users`、`auth_identities`、`user_affiliates`，并扫描 `payment_orders`、`redeem_codes`、`payment_audit_logs`；`users.balance` 只做对账和人工确认快照。
- Sub2API 用户账号：共用 `users` / `auth_identities`，线上当前只有 `email` provider。
- Sub2API Admin API：上游账号和上游用量调阅优先使用 `x-api-key`；`admin/accounts` 不是用户账号体系。
- Sub2API 上游账号监控第一版必须做；它是模型账号/渠道账号运营看板，不参与用户返利和账务计算。
- Sub2API 原生 affiliate：已在线开启，服务 Sub2API 自己的 `/affiliate` 单级邀请返利；Sub2Rebate 会读取 `user_affiliates.aff_code` 展示原生邀请链接，但无限级返利仍独立实现。
- 提现最低金额：50 元。
- 提现每日次数：默认 1 次，可后台配置。
- 支付宝自动充值/自动提现第一版先空着，只预留支付模块和人工提现流程。
- 后台管理权限优先跟随 Sub2API `users.role = admin`，本系统用 `sub2_user_roles` 做补充限制。
- 暂不禁止管理员直接在 Sub2API 改余额，但这类变更只做对账和人工确认，不自动发放返利。

## 6. 当前待确认

- 返利冻结天数是否后续启用；第一版默认 0 天，即返利确认后直接可提现。

## 6.1 已确认补充决策（2026-06-13）

- 余额调整与 API 额度充值不冲突，两者操作不同余额体系：余额调整操作的是返利余额（Sub2Rebate），API 额度充值操作的是 Sub2API 的 API 调用额度。
- 余额调整不再作为独立页面，改为用户管理页面的弹窗（当前 `AdminUsersView` 已实现）。
- `AdminBalanceAdjustView` 独立页面将废弃，路由移除。
- 推荐关系页面（管理后台）需支持：移动画布、搜索指定用户查看推荐关系、点击节点查看用户信息。
- 推荐关系页面后续可能有很多层级，需做性能优化（懒加载子树、虚拟渲染）。
- 推广用户端推荐关系页面只能看自己及以下关系，上级不可看，后端强制当前用户为根节点。
- 推广员需要支持扫码支付增加额度功能，支付回调判断成功后自动增加额度并触发返利。
- 返利配置中心每个配置项必须有 Tooltip 说明，鼠标悬停展示，说明内容参考根目录 `方案研讨.md` 第 26 节。

## 7. 下一个建议动作

按 `docs/IMPLEMENTATION_PLAN.md` 拆分任务执行，下一步进入配置中心和邀请关系：

```text
backend/    Laravel 后端
frontend/   Vue 3 用户前端
docs/       项目文档
```

建议执行顺序：

1. 继续 B11 Sub2API 扫描集成：`payment_orders`、`redeem_codes`、`payment_audit_logs` 转换为幂等 `rebate_events`。
2. 继续 B10 复杂后台页面：邀请关系可视化、用户余额调整弹窗、充值事件补录入口。
3. 继续前端用户端页面和 API 联调。

## 8. 已落地代码骨架

- `backend/`：Laravel 12 后端骨架。
- `backend/routes/api.php`：`GET /api/v1/health` 健康检查。
- `backend/app/Support/ApiResponse.php`：统一 API 响应工具。
- `backend/app/Support/ApiError.php`：基础错误码。
- `backend/app/Modules/*`：B1 模块基础结构和 ServiceProvider。
- `backend/app/Modules/Sub2Api/Repositories/Sub2ApiUserRepository.php`：读取 Sub2API `users` / `user_affiliates`。
- `backend/app/Modules/Auth/Services/Sub2RebateAuthService.php`：Sub2API bcrypt 登录校验和 Sanctum token 签发。
- `backend/app/Modules/User/Services/AccountProfileService.php`：用户资料与邀请链接聚合。
- `backend/app/Modules/Config/Services/ConfigService.php`：配置读取、默认配置补齐和缓存。
- `backend/app/Modules/Invite/Services/InviteService.php`：邀请码、绑定、上级链路、邀请树和邀请记录。
- `backend/app/Modules/Payment/Services/RechargeEventService.php`：充值事件创建、幂等写入和金额换算快照。
- `backend/app/Modules/Milestone/Services/MilestoneService.php`：里程碑奖励判断、流水写入和事件处理。
- `backend/app/Modules/Rebate/Services/RebateBalanceService.php`：返利余额增加基础服务。
- `backend/app/Modules/Rebate/Services/DecayRebateCalculator.php`：衰减系数权重、归一化和尾差计算。
- `backend/app/Modules/Rebate/Services/DecayRebateService.php`：多级衰减返利事件处理。
- `backend/app/Modules/Withdraw/Services/WithdrawService.php`：提现配置、提现账号、提现申请和记录分页。
- `backend/app/Modules/Audit/Services/AuditLogService.php`：审计日志记录服务。
- `backend/app/Modules/Risk/Services/RiskService.php`：黑名单/提现冻结风控服务。
- `backend/app/Providers/Filament/AdminPanelProvider.php`：Filament 后台入口配置。
- `backend/app/Filament/Pages/Auth/Login.php`：复用 Sub2API 登录适配的后台登录页。
- `backend/app/Modules/Admin/Services/AdminAccessService.php`：后台管理员权限判断服务。
- `backend/app/Modules/Admin/Services/AdminWithdrawService.php`：后台提现审核、拒绝和打款服务。
- `backend/app/Modules/Sub2Api/Services/Sub2ApiAdminClient.php`：Sub2API Admin API client，使用 `x-api-key` 调用上游账号监控接口。
- `backend/app/Modules/Sub2Api/Services/Sub2ApiAdminClient.php`：已扩展支持 Sub2API 用户余额调整接口，用于管理端 API 额度充值。
- `backend/app/Modules/Sub2Api/Services/Sub2ApiUpstreamAccountSyncService.php`：Sub2API 上游账号列表/详情同步服务，失败只写审计并记录最近错误。
- `backend/app/Modules/Sub2Api/Models/Sub2ApiUpstreamAccount.php`：上游模型账号/渠道账号本地监控快照。
- `backend/app/Filament/Resources/*Resource.php`：用户、配置、提现、返利、风控、审计和上游账号监控后台 Resource。
- `backend/database/migrations/2026_06_13_000001_create_users_table.php` 等：本地用户、Sub2API affiliate 快照、Sanctum token、角色标记、邀请路径基础表。
- `backend/database/migrations/2026_06_13_000005_create_config_items_table.php`：配置中心表。
- `backend/database/migrations/2026_06_13_000006_create_payment_records_table.php`：支付/充值记录表。
- `backend/database/migrations/2026_06_13_000007_create_rebate_events_table.php`：返利事件入口表。
- `backend/database/migrations/2026_06_13_000008_create_user_rebate_progress_table.php`：里程碑进度表。
- `backend/database/migrations/2026_06_13_000009_create_rebate_balances_table.php`：返利余额表。
- `backend/database/migrations/2026_06_13_000010_create_rebate_records_table.php`：返利流水表。
- `backend/database/migrations/2026_06_13_000011_create_withdraw_accounts_table.php`：提现账号表。
- `backend/database/migrations/2026_06_13_000012_create_withdraw_records_table.php`：提现记录表。
- `backend/database/migrations/2026_06_13_000013_create_audit_logs_table.php`：审计日志表。
- `backend/database/migrations/2026_06_13_000014_create_risk_flags_table.php`：风控标记表。
- `backend/database/migrations/2026_06_13_000015_create_sub2api_upstream_accounts_table.php`：Sub2API 上游模型账号/渠道账号监控快照表。
- `backend/database/migrations/2026_06_14_000001_add_referral_paths_path_prefix_index.php`：PostgreSQL 下为 `referral_paths.path` 增加前缀查询索引。
- `backend/tests/Feature/AuthAndProfileTest.php`：B2 登录、资料聚合、错误密码、禁用用户和未登录 Feature Test。
- `backend/tests/Feature/ConfigItemsTest.php`：B3 配置读取和默认配置 Feature Test。
- `backend/tests/Feature/InviteTest.php`：B4 邀请绑定、树状层级和链路 Feature Test。
- `backend/tests/Feature/RechargeEventTest.php`：B5 充值事件幂等和管理员补录服务 Feature Test。
- `backend/tests/Feature/MilestoneTest.php`：B6 里程碑奖励、跨额、重复处理 Feature Test。
- `backend/tests/Feature/DecayRebateTest.php`：B7 多级衰减返利、归一化和幂等 Feature Test。
- `backend/tests/Feature/WithdrawTest.php`：B8 提现配置、账号、申请和记录 Feature Test。
- `backend/tests/Feature/AuditRiskTest.php`：B9 审计与风控 Feature Test。
- `backend/tests/Feature/AdminWithdrawTest.php`：B10 后台提现审核动作 Feature Test。
- `backend/tests/Feature/FilamentAdminTest.php`：B10 Filament 后台入口、权限和 Resource 可访问 Feature Test。
- `backend/tests/Feature/Sub2ApiUpstreamAccountTest.php`：B12 Admin API `x-api-key`、上游账号同步、详情同步、失败审计和不写返利事件 Feature Test。
- `frontend/`：Vue 3 + Vite + TypeScript 前端骨架。
- `frontend/src/api/`：按模块拆分的 API client。
- `frontend/src/router/`：基础路由。
- `frontend/src/stores/`：Pinia 状态入口。
- `frontend/src/layouts/`、`frontend/src/views/`：基础布局和占位页面。
- `frontend/src/views/admin/AdminRelationshipView.vue`：推荐关系页面已支持搜索、画布缩放拖拽、树节点展开/收起和点击查看详情。
- `frontend/src/views/admin/AdminUsersView.vue`：用户管理中的余额调整弹窗已要求管理员密码二次确认，金额为数字输入，默认备注为“返利金额调整”。
- `frontend/src/views/admin/AdminApiQuotaView.vue`：API 额度管理已接入真实后端接口，默认原因“充值”、备注“余额充值”，用于调整 Sub2API API 调用额度。
- `frontend/src/views/promotion/PromotionView.vue`、`frontend/src/views/account/AccountView.vue`：用户端展示 Sub2API 原生邀请链接，不再展示 Sub2Rebate 自有分销邀请码。
- `frontend/src/views/promotion/MyRelationshipView.vue`：我的推荐关系已接真实邀请树接口。

## 9. Sub2API 调研状态

- 公网入口：`https://api.sjiaa.cc.cd/` 和 `http://154.44.9.60:8080/` 在 2026-06-13 返回 `HTTP 200`。
- 源码版本：`Wei-Shaw/sub2api @ e34ad2b`。
- 生产库：`sub2api-postgres` 使用 PostgreSQL 18，库名/用户为 `sub2api`，共 74 张表。
- 用户账号：已确认 `/api/v1/admin/users`，当前 2 个用户；`auth_identities` 当前只有 `email`。
- 管理鉴权：推荐 `x-api-key`，也支持管理员 JWT。
- 上游账号调阅：已确认 `/api/v1/admin/accounts`、`/api/v1/admin/accounts/{id}/usage`、`stats`、`today-stats`；它们只用于上游账号运营监控。
- 上游账号监控首版：Sub2Rebate 已通过 `SUB2API_BASE_URL`、`SUB2API_ADMIN_API_KEY` 调用 Admin API，并在本地保存最近一次快照和错误。
- 充值事件源：当前生产库优先 `redeem_codes`，未来接支付后加 `payment_orders`、`payment_audit_logs`；余额快照不自动触发返利。
- 只读连接：已创建 `sub2rebate_ro` 只读账号，本地通过 SSH 隧道验证可读 `users`、`user_affiliates`、`redeem_codes`。
- Sub2API 原生 affiliate：`affiliate_enabled = true`，现有 2 个用户已补齐 `user_affiliates` 档案，`/affiliate` 路径返回 `HTTP 200`。
- Sub2API 真实邀请链路：2026-06-14 创建测试账号 ID 3 后确认 `user_affiliates.inviter_id = 2`，验证完成后 `registration_enabled = false` 已恢复。
- 不推荐：只监控 `users.balance` 或把 Sub2API 原生单级 affiliate 当作 Sub2Rebate 主业务。
- 详情：`docs/SUB2API_INTEGRATION_RESEARCH.md`。

## 10. 验证状态

- 提交前复验：2026-06-15 使用项目 PHP 运行时执行 `php artisan test`，148 个测试、699 个断言通过；使用 Node 24 执行 `vue-tsc --noEmit` 和 `vite build` 通过，仍有 Element Plus 大 chunk 提示。
- 文档：2026-06-14 执行 `php artisan route:list --path=api/v1`，当前显示 44 条 API 路由，并已同步到 `docs/API_CONTRACT.md`。
- 登录联调：2026-06-14 使用真实 Sub2API 管理员账号和普通用户账号验证登录成功；本地页面显示管理员用户可进入管理端，普通用户按用户端权限使用。
- API 扫描：2026-06-14 自动扫描管理员 25 个核心 GET 接口均返回 200；普通用户核心接口返回 200，访问管理端按预期返回 403；后端新增日志无 500。
- 前端：2026-06-14 使用 Node 22 执行 `vue-tsc --noEmit` 通过；`vite build` 通过；浏览器在当前工作区前端 `127.0.0.1:5174` 复扫，推荐关系页不再出现 undefined；仅有 Element Plus `el-pagination small` 废弃 warning。
- 后端：2026-06-14 执行全量 `php artisan test` 通过：59 个测试，395 个断言。
- 后端：2026-06-14 管理端调额、返利层级保存和 Sub2API 邀请链路更新后，执行全量 `php artisan test` 通过：63 个测试，422 个断言。
- 后端：2026-06-14 安全与并发审计修复后，执行全量 `php artisan test` 通过：141 个测试，668 个断言。
- 前端：2026-06-14 使用 Node 22 执行 `vue-tsc --noEmit` 通过；`vite build` 通过。构建仍提示 Element Plus chunk 约 933 kB，后续可按需引入或 manualChunks 优化。
- 生产联调：2026-06-14 临时打开 Sub2API 注册创建测试账号 ID 3，确认生产 `user_affiliates.inviter_id = 2`；本地 `InviteService::syncFromSub2Api` 同步出 `path = 2/3`、`depth = 1`，用户 2 邀请树和邀请记录均包含测试用户 3；注册开关已恢复关闭。
- 前端：2026-06-14 使用 Node 22 执行 `vue-tsc --noEmit` 通过；`vite build` 通过。
- 前端：使用 Node 22 执行 `npm run build` 通过。
- 前端：2026-06-13 修复 `AdminRelationshipView.vue` 重复类型声明和空递归节点后，使用 Node 22 执行 `npm run build` 通过。
- 前端：Mock 模式浏览器冒烟验证 `/admin/relationships` 通过，推荐关系树和用户详情弹窗可用，控制台无 error。
- 前端：2026-06-13 目标完成审计时再次使用 Node 22 执行 `npm run build` 通过。
- 后端：项目内已安装 PHP 8.5.7 和 Composer 2.10.1，入口在 `.tools/conda/php/bin`。
- 后端：PHP 语法检查已通过；`backend/composer.lock` 已按 `config.platform.php = 8.2.0` 重新生成。
- 后端：完整 `composer install` 已完成；为避免 GitHub `laravel/pint` 下载失败阻塞测试，已移除 Pint dev 依赖。
- 后端：`php artisan route:list --path=api/v1` 可列出 15 个接口。
- 后端：临时 sqlite 迁移通过；服务层 smoke 覆盖 Sub2API fake 用户登录、本地用户同步、Sanctum token、`user_affiliates.aff_code` 聚合和资料邀请链接；HTTP smoke 覆盖 health 与未登录 401。
- 后端：`php artisan test` 通过：48 个测试，336 个断言。
- 后端：2026-06-13 目标完成审计时再次执行 `php artisan test` 通过：48 个测试，336 个断言；`php artisan route:list --path=api/v1` 可列出 15 个 API 路由。
- 后端：`php artisan filament:about` 确认 Filament 3.3.54 可用。
- 后端：`php artisan route:list --path=admin` 可列出 24 个后台路由。
- 后端：本机 PHP 8.5 高于目标平台 PHP 8.2，`openspout` 平台检查不支持 PHP 8.5；部署/CI 建议使用 PHP 8.2/8.3/8.4。
- 环境注意：本机默认 PATH 可能命中 Node 14，前端命令需先切到 Node 18+。
