# 全局开发日志

> 记录项目每次重要开发、文档调整和决策变化。最新记录放在最上面。

## 2026-06-15：提交前进度快照

目标：

- 在本次提交前记录当前已完成范围，方便后续从 Git 历史恢复上下文。
- 明确下一步仍应继续 B11 Sub2API 充值事件扫描器和复杂后台页面。

完成：

- 后端 Laravel 12 基础骨架、模块服务层、Admin API、用户端 API、返利事件队列消费和安全并发修复已进入当前工作区。
- 前端 Vue 3 管理端/用户端基础页面已接入真实 API 优先模式，保留 Mock 回退能力。
- `docs/API_CONTRACT.md` 已作为唯一接口口径维护，当前 `/api/v1` 路由索引为 44 条。
- `docs/PROJECT_STATE.md`、模块文档、架构/决策文档已同步当前实现边界和后续路线。
- `.gitignore` 已补充本机环境、测试缓存、构建产物和服务器资料文件忽略规则，避免敏感或生成文件进入提交。

验证：

- 本次提交前使用项目 PHP 运行时执行后端全量测试通过：148 个测试，699 个断言。
- 本次提交前使用 Node 24 执行 `vue-tsc --noEmit` 通过。
- 本次提交前使用 Node 24 执行 `vite build` 通过；仍提示 Element Plus chunk 约 933 kB，后续可单独拆包优化。

## 2026-06-14：安全与并发审计修复

目标：

- 修复返利余额、后台调额、提现审核等金额链路并发风险。
- 补齐登录/提现限流、封禁 token 失效、Sanctum token 过期、返利事件自动消费兜底。
- 纠正 Sub2API 密码体系与本地改密接口不一致的问题。
- 修复 API 文档路由数和用户个性化返利配置说明不一致。

完成：

- `RebateBalanceService::addAvailable()` 改为数据库原子自增，避免并发返利入账覆盖。
- 后台余额调整、提现申请、提现拒绝解冻、标记打款在事务中锁定余额行。
- 里程碑/衰减服务不再各自标记 `rebate_events.status=processed`，统一由 `ProcessRebateEventJob` 在两个阶段成功后标记。
- 登录接口增加限流；提现申请接口增加限流。
- 新增 `EnsureActiveUser` 中间件；管理员封禁用户时删除该用户 Sanctum tokens。
- 新增 `config/sanctum.php`，token 默认 7 天过期，可用 `SANCTUM_EXPIRATION` 调整。
- `account/change-password` 改为明确返回 Sub2API 统一管理密码的错误，不再写本地 `users.password`。
- `account/profile` 更新 email 增加本地唯一校验。
- `InviteService` / `AccountProfileService` 对 Sub2API 只读连接故障降级为本地数据，不让邀请/资料接口直接 500；邀请同步递归限制为 50 层。
- 新增 `referral_paths.path text_pattern_ops` PostgreSQL 前缀索引迁移。
- 后台配置更新增加关键金额/比例/次数的基础范围校验。
- 审计日志默认记录当前请求 IP 和 User-Agent。
- 注册 Laravel Schedule：每分钟执行 `rebate:process-pending --limit=100`。
- 前端 401 跳转增加防抖；提现页小屏横向溢出修复。
- 新增 `config/cors.php`，生产跨域域名可通过 `CORS_ALLOWED_ORIGINS` 配置。

验证：

- 后端全量测试通过：141 个测试，668 个断言。
- 前端使用 Node 22 执行 `vue-tsc --noEmit` 通过。
- 前端使用 Node 22 执行 `vite build` 通过；仍提示 Element Plus chunk 约 933 kB，后续可单独拆包优化。

## 2026-06-14：Sub2API 真实邀请链路生产验证

目标：

- 临时打开 Sub2API 注册，创建真实测试账号，验证原生 `user_affiliates.inviter_id` 能同步为 Sub2Rebate 本地邀请层级。
- 验证完成后立即恢复 Sub2API 注册关闭。

完成：

- 确认生产公开设置初始状态为 `registration_enabled = false`、`affiliate_enabled = true`。
- 使用普通用户 ID 2 的 Sub2API 原生邀请码 `8UDG84TQD7BD` 创建测试账号 ID 3：`sub2rebate-test-1781436503668-ae5117@example.com`。
- 验证完成后已恢复 `registration_enabled = false`，公开设置再次确认注册关闭。
- 生产只读库确认测试账号 `user_affiliates.inviter_id = 2`，测试账号自身 `aff_code = WRHZJ7QTKULU`。
- 本地 Sub2Rebate 调用 `InviteService::syncFromSub2Api` 后，`referral_paths` 写入 `user_id = 3`、`parent_user_id = 2`、`path = 2/3`、`depth = 1`。

验证：

- 用户 2 的邀请树服务输出包含测试用户 3，层级 `level = 1`。
- 用户 2 的邀请记录服务输出包含测试用户 3，`total = 1`。

注意：

- 测试账号保留在生产 Sub2API 中作为本次验证留痕；未写入任何明文密码、token 或服务器凭据。

## 2026-06-14：真实 Sub2API 账号登录联调与管理端异常修复

目标：

- 按“使用 Sub2API 账号作为新系统登录账号”的方案做真实联调。
- 修复真实数据下管理端页面字段不匹配、空用户名/昵称导致的前端异常。
- 确认本地迁移完整后，管理端核心只读接口不再返回 500。

完成：

- 确认登录链路仍以 Sub2API 用户表密码哈希为准：用户输入密码后由后端通过 `password_verify` 校验 Sub2API `users.password`，再同步本地用户并签发 Sub2Rebate 自己的 Sanctum token。
- 使用真实管理员账号和普通用户账号完成登录验证；不把测试密码写入代码、文档或日志。
- 补跑本地 Laravel 迁移，解决真实管理页访问余额记录时 `audit_logs` 表未创建导致的 500。
- `AdminUserController` 补齐前端管理用户列表需要的展示字段：`nickname`、`avatar`、`parentNickname`、`directInviteCount`、`totalRebateAmount`、`totalPaidAmount`。
- `AdminBalanceController::records()` 将审计日志转换为前端余额调整记录结构，避免真实接口分页结构和前端弹窗预期不一致。
- `AdminRelationshipController` 补齐推荐关系树节点字段：`avatar`、`level`、`totalRecharge`、`directReferrals`、`status`，修复真实页面显示 `ID: undefined`、`直邀 undefined 人`。
- 前端新增统一用户显示工具 `userName/userAccount/userInitial`，管理用户、推荐关系、用户返利、API 额度和余额调整页改用统一兜底显示，避免 Sub2API 空 `username/nickname` 触发 `charAt` 异常。
- `frontend/src/api/admin.ts` 适配真实接口返回结构：推荐关系树、余额调整记录、用户返利覆盖配置都统一转换成页面当前需要的数据形态。

验证：

- 后端全量测试通过：59 个测试、395 个断言。
- 前端类型检查通过：`vue-tsc --noEmit`。
- 前端生产构建通过：`vite build`。
- 本地后端健康检查返回 200，前端 dev server 返回 200，Sub2API 数据库 SSH 隧道 `127.0.0.1:15432` 可连接。
- 自动 API 扫描通过：管理员 25 个核心 GET 接口均返回 200；普通用户核心接口返回 200，访问管理端按预期返回 403；无 500。
- 真实浏览器验证当前工作区前端 `127.0.0.1:5174`：管理员登录后推荐关系页节点正常显示 `Song / ID: 1 / Top Master / 直邀 0 人`，不再出现 undefined。
- 浏览器复扫未出现新的前端 error 或后端 500；仅剩 Element Plus `el-pagination small` 兼容性 warning，后续可单独清理。

注意：

- 本地库新增迁移后必须执行 `php artisan migrate --force`，否则管理端审计/余额相关接口会因缺表返回 500。
- 明文服务器密码、测试账号密码只能放在本机临时资料或密钥管理工具中，不进入项目文档和 Git。

## 2026-06-14：第二轮 — 前端从 Mock 切换到真实 API

目标：

- 前端默认走真实后端 API，联调模式成为开发主流程。
- 保留 Mock 开关，开发者可随时切回。

完成：

- 将 `.env` 中 `VITE_USE_MOCK` 默认值从 `true` 改为 `false`。
- 配置 Vite dev server proxy，将 `/api` 请求代理到 `http://127.0.0.1:8000`（Laravel 后端）。
- 新增 `.env.development` 文件，记录开发环境配置和 Mock 切换说明。
- 补齐后端缺失接口：`PUT /account/profile`、`POST /account/change-password`。
- 更新 `docs/API_CONTRACT.md` 路由索引（41 → 43）。
- 更新 `frontend/README.md`，补充联调开发工作流说明。

验证：

- 前端 API 层所有模块路径已与后端 `routes/api.php` 逐条对齐（auth、account、invite、withdraw、dashboard、promotion、rebate、admin 全部覆盖）。
- 后端响应格式（camelCase + `{ code, message, data }`）与前端 `ApiRes<T>` 类型完全匹配。
- TypeScript 类型无需修改，mock 数据结构和真实 API 结构一致。

开发流程：

```bash
# 终端 1
cd backend && php artisan serve

# 终端 2
cd frontend && npm run dev
```

如需回退到 Mock 模式，只需将 `.env` 中 `VITE_USE_MOCK` 改为 `true`。

## 2026-06-14：接口文档规则收口

目标：

- 明确项目必须有持续维护的接口文档。
- 让后续新增、修改、删除接口时都有固定文档入口，避免前后端联调靠猜字段。

完成：

- 将 `docs/API_CONTRACT.md` 明确为前后端联调唯一接口口径。
- 新增接口文档维护规则：接口新增、修改、删除必须同步记录方法、路径、认证、参数、请求示例、响应示例和错误场景。
- 补充当前 `/api/v1` 已实现接口索引，共 41 条路由。
- 补齐当前 Admin API 文档，覆盖后台仪表盘、用户管理、提现审核、配置、余额调整、推荐关系、审计日志、用户个性化返利配置。
- 校正用户端仪表盘、推广统计、转化记录、返利记录等接口示例，使字段与当前 Controller 返回一致。
- 更新 `docs/DEVELOPMENT_PROCESS.md`、`docs/AGENTS.md`、`docs/README.md`、`docs/AI_CONTEXT.md`，统一要求接口变更必须同步更新 `docs/API_CONTRACT.md`。

验证：

- 已执行 `php artisan route:list --path=api/v1`，当前显示 44 条 API 路由。

## 2026-06-13：第一轮实施 — 补齐 Admin API + 返利消费 + 用户端接口

目标：

- 解除前后端联调阻塞：后端服务层已完整但缺少面向前端的 HTTP 接口。
- 补齐返利事件自动消费入口（Job + Command）。
- 补齐 Admin API 路由组，让前端管理后台可以从 Mock 切换到真实后端。
- 补齐用户端仪表板、返利记录、推广统计接口。

完成：

- 新增 `ProcessRebateEventJob`：接收 `RebateEvent`，依次调 MilestoneService → DecayRebateService，失败标记 `status=failed` + `error_message`。
- 新增 `rebate:process-pending` artisan 命令：扫描 pending 事件批量 dispatch Job。
- 新增 `EnsureAdmin` 中间件，注册别名 `admin`，统一拦截非管理员请求返回 403。
- 新增 Admin API 路由组（`v1/admin` 前缀，`auth:sanctum` + `admin` 中间件），包含 8 个 Controller：
  - `AdminDashboardController`：总用户数、今日新增、总返利、待审核提现、返利余额总量、趋势图。
  - `AdminUserController`：分页用户列表、搜索、封禁、解封、角色切换。
  - `AdminWithdrawController`：分页提现列表、审核通过、拒绝解冻、标记打款。
  - `AdminConfigController`：读取全量配置、批量更新配置。
  - `AdminBalanceController`：管理员余额调整、余额调整记录查询。
  - `AdminRelationshipController`：推荐关系树查询。
  - `AdminAuditLogController`：分页审计日志 + actionType 筛选。
  - `AdminRebateController`：用户个性化返利设置。
- 补齐用户端接口：
  - `DashboardController`：summary、rebate-trends、recent-activities。
  - `RebateRecordController`：返利流水分页查询。
  - `PromotionController`：推广统计、转化记录、推广返利记录。
- `ConfigService` 新增 `updateBatch()`：批量更新配置值 + 清缓存 + 写审计日志。
- 新增 Feature/Unit 测试覆盖全部新增接口、Job/Command、失败标记逻辑。

验证：

- `php artisan test` 通过：59 个测试，389 个断言。

## 2026-06-13：完成 S1/B0/B1/B2/F0 目标审计

目标：

- 对当前 goal 的首批实际开发范围做完成性审计。
- 用当前 worktree 和命令结果证明 S1 API 契约、B0/B1/B2 后端骨架与认证基础、F0 前端骨架已经完成。
- 再次核对方案研讨和 Sub2API 集成边界，避免把余额快照或上游账号监控接入返利账务。

完成：

- S1：`docs/API_CONTRACT.md` 已覆盖统一响应、认证、用户端 API、后台资源口径、错误码、状态枚举、分页格式和金额字段格式。
- B0/B1：`backend/` Laravel 12 骨架、模块 ServiceProvider、统一响应、错误码、迁移、Feature Test 基础已存在。
- B2：Sub2API 用户读取、`user_affiliates.aff_code` 读取、bcrypt 登录校验、本地用户同步、Sanctum token 签发和账户资料聚合已落地。
- F0：`frontend/` Vue 3 + Vite + TypeScript 骨架、Vue Router、Pinia、Axios 请求封装、Element Plus、用户端/管理端基础页面和 Mock 数据已存在。
- Sub2API 边界：`users.balance` / `users.total_recharged` 只作为对账和人工确认；`payment_orders`、`redeem_codes`、`payment_audit_logs` 才是充值事件扫描方向；`admin/accounts` 只做上游模型账号/渠道账号运营监控，不参与返利事件或用户账务。

验证：

- 后端 `php artisan test` 通过：48 个测试，336 个断言。
- 后端 `php artisan route:list --path=api/v1` 可列出 15 个 API 路由。
- 前端使用 Node 22 执行 `npm run build` 通过。
- `Sub2ApiUpstreamAccountTest` 覆盖 `x-api-key`、上游账号同步失败审计，以及同步上游账号不写入 `rebate_events`。

结论：

- 当前 goal 要求的 S1/B0/B1/B2/F0 已完成并验证。
- 下一阶段建议进入 B11 Sub2API 充值事件扫描器，接入 `payment_orders`、`redeem_codes`、`payment_audit_logs` 到幂等 `rebate_events`。

## 2026-06-13：复核 S1/B0/B1/B2/F0 并修复前端构建

目标：

- 按已确认业务口径复核 S1 API 契约、B0/B1/B2 后端骨架与认证基础、F0 前端骨架。
- 持续核对 `方案研讨.md` 与 Sub2API 集成边界，避免把上游账号监控误接入返利账务。
- 确保当前代码能通过后端测试和前端生产构建。

完成：

- 复核 `docs/API_CONTRACT.md`，已覆盖统一响应、认证、错误码、状态枚举、金额字段、分页、用户端接口和后台接口口径。
- 复核后端认证基础：`POST /api/v1/auth/login`、`POST /api/v1/auth/logout`、`GET /api/v1/auth/me` 已接入 Sanctum，登录复用 Sub2API 用户并同步本地用户。
- 复核前端骨架：Vue Router、Pinia、Axios token 拦截、用户端页面、管理端页面和 Mock 数据已存在。
- 修复 `AdminRelationshipView.vue` 中重复声明 `RelationshipNode` 导致的类型错误。
- 将推荐关系页的占位递归组件补成实际树节点渲染，支持展开/收起和点击查看用户详情。
- 确认 Sub2API 上游账号监控仍只作为运营监控，不参与 `rebate_events`、`payment_records` 或用户返利账务。

验证：

- 后端 `php artisan test` 通过：48 个测试，336 个断言。
- 前端使用 Node 22 路径执行 `npm run build` 通过。
- 前端 Mock 模式启动 Vite 后，浏览器验证 `/admin/relationships` 可渲染推荐关系树，点击节点可打开“用户详情”弹窗，控制台无 error。

注意：

- 当前 shell 默认 `npm` 可能命中旧版本，前端构建需确保 Node 22 bin 在 `PATH` 前面。
- B11 Sub2API 充值事件扫描器仍未实现，下一步应继续接 `payment_orders`、`redeem_codes`、`payment_audit_logs` 到幂等返利事件入口。

## 2026-06-13：收口 B10 基础后台 Resource + B12 上游账号监控首版

目标：

- 继续推进 Filament 后台从入口变成可运营页面。
- 落地 Sub2API 上游账号监控第一版，并确保它不参与返利账务。

完成：

- 新增基础后台 Resource：用户管理、配置中心、提现审核、返利事件、返利流水、返利余额、里程碑进度、风控标记、审计日志、Sub2API 上游账号监控。
- 配置修改新增备注要求，并写入 `audit_logs`。
- 提现审核 Resource 接入审核通过、拒绝解冻、标记打款动作。
- `User` 实现 Filament `HasName`，后台显示使用 `username/email/user_id`。
- 新增 `sub2api_upstream_accounts` 监控快照表、`Sub2ApiUpstreamAccount` 模型。
- 新增 `Sub2ApiAdminClient`，使用 `x-api-key` 调用 Sub2API Admin API。
- 新增 `Sub2ApiUpstreamAccountSyncService`，支持同步上游账号列表、详情、用量、统计和今日统计。
- 上游账号同步失败只记录最近错误和审计日志，不阻塞登录、返利、提现。
- 新增 `Sub2ApiUpstreamAccountTest`，覆盖 `x-api-key`、列表/详情同步、失败审计和不写入 `rebate_events`。
- 更新项目状态、实施计划、Admin 模块文档和 Sub2API 集成文档。

验证：

- `php artisan test` 通过：48 个测试，336 个断言。
- `php artisan route:list --path=api/v1` 可列出 15 个 API 路由。
- `php artisan route:list --path=admin` 可列出 24 个后台路由。
- `php artisan filament:about` 显示 Filament v3.3.54。

限制：

- B11 Sub2API 充值事件扫描器仍未实现。
- 邀请关系可视化、用户余额调整弹窗、充值事件补录入口和后台首页统计仍待继续。

## 2026-06-13：落地 Filament 后台入口和登录策略

目标：

- 建立实际可访问的 Filament 后台入口。
- 后台登录复用 Sub2API 用户账号和 bcrypt 密码校验，只允许管理员进入。

完成：

- 新增 `AdminPanelProvider`，后台入口为 `/admin`。
- 新增自定义 Filament 登录页 `App\Filament\Pages\Auth\Login`。
- 后台登录复用 `Sub2RebateAuthService::validate()`，同步本地用户但不签发用户端 Sanctum token。
- `User` 实现 `FilamentUser::canAccessPanel()`，只允许 `role=admin` 且 `status=active` 的用户访问后台。
- 新增 `FilamentAdminTest`，覆盖后台登录页、未登录跳转和后台访问权限。
- 更新 Admin 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：43 个测试，311 个断言。
- `php artisan route:list --path=admin` 可列出 3 个后台路由。
- `php artisan route:list --path=api/v1` 可列出 15 个 API 路由。

限制：

- Filament Resource 页面、配置页面、审计页面、风控页面和上游账号监控页面继续在 B10/B12 后续实现。

## 2026-06-13：落地 B10 后台核心动作基础

目标：

- 在完整 Filament 页面之前，先落地后台管理员权限判断和提现审核核心业务动作。
- 保证提现审核状态流转、余额解冻/扣减和审计日志可测试。

完成：

- 确认 Filament 包已安装可用，当前版本 v3.3.54。
- 新增 `AdminAccessService`，当前基于本地同步用户 `role=admin` 且 `status=active` 判断管理员。
- 新增 `AdminWithdrawService`，支持提现审核通过、拒绝解冻、标记打款。
- 后台敏感动作必须填写备注。
- 提现审核状态不能跳过：`pending -> approved -> paid`，拒绝只允许从 `pending/approved` 进入。
- 拒绝提现会解冻余额；标记打款会扣减冻结金额并累计已提现金额。
- 后台提现审核动作写入审计日志。
- 新增 `AdminWithdrawTest`。
- 更新 Admin 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：40 个测试，304 个断言。
- `php artisan route:list --path=api/v1` 可列出 15 个接口。
- `php artisan filament:about` 显示 Filament v3.3.54。

限制：

- Filament PanelProvider、Resource 页面、完整 Sub2API admin 登录策略、配置页面、审计页面、风控页面和上游账号监控页面仍待继续实现。

## 2026-06-13：落地 B9 审计风控基础

目标：

- 建立统一审计日志入口，让返利、提现和后续后台敏感操作可追溯。
- 建立基础风控标记，让黑名单和提现冻结能阻止用户提现。

完成：

- 新增 `audit_logs` 表、`AuditLog` 模型和 `AuditLogService`。
- 新增 `risk_flags` 表、`RiskFlag` 模型和 `RiskService`。
- 提现申请成功后写入 `withdraw.apply` 审计日志。
- 里程碑返利发放写入 `rebate.milestone_granted` 审计日志。
- 多级衰减返利发放写入 `rebate.decay_granted` 审计日志。
- 提现申请接入风控检查，黑名单和提现冻结会阻止提现。
- 新增 `AuditRiskTest`，覆盖提现审计、黑名单拦截和返利审计。
- 更新 Audit/Risk 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：36 个测试，287 个断言。
- `php artisan route:list --path=api/v1` 仍为 15 个接口；B9 当前只落服务层基础。

限制：

- 后台审计列表、详情、风控管理页面待 B10 Filament 后台实现。
- 配置修改、提现审核、余额手工调整、邀请关系修正等后台操作审计待 B10 接入。

## 2026-06-13：落地 B8 用户提现申请基础

目标：

- 基于 `rebate_balances` 实现用户提现申请，先完成人工审核前的用户侧闭环。
- 提交提现后冻结可提现余额，生成待审核记录。

完成：

- 新增 `withdraw_accounts` 表和模型。
- 新增 `withdraw_records` 表和模型。
- 新增 `WithdrawService`，支持提现配置读取、提现账号保存/读取、提现申请、提现记录分页。
- 新增 `WithdrawController` 和用户端提现接口：
  - `GET /api/v1/withdraw/config`
  - `GET /api/v1/withdraw/account`
  - `POST /api/v1/withdraw/account`
  - `POST /api/v1/withdraw/apply`
  - `GET /api/v1/withdraw/records`
- 实现最低提现金额、每日提现次数、提现账号必填、余额不足校验。
- 提现申请成功后，`available_amount` 减少、`frozen_amount` 增加，提现记录状态为 `pending`。
- 新增 `WithdrawTest`。
- 更新 Withdraw 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：33 个测试，279 个断言。
- `php artisan route:list --path=api/v1` 可列出 15 个接口。

限制：

- 管理员审核、拒绝解冻、标记打款、黑名单限制和审计日志待 B9/B10 继续。

## 2026-06-13：落地 B7 多级衰减返利服务层

目标：

- 在里程碑阶段结束后，按无限级上级链路和衰减系数分配正常返利。
- 确保返利池总额守恒，直接上级金额最大，重复处理不重复发放。

完成：

- 新增 `DecayRebateCalculator`，负责权重计算、归一化分配和尾差处理。
- 新增 `DecayRebateService`，负责消费 `rebate_events` 的正常返利部分。
- 正常返利金额按 `rebate.pool_ratio` 计算返利池，按 `rebate.decay_factor` 计算各级权重。
- 复用 B4 上级链路，支持无限级上级。
- 写入 `rebate_records` 的 `decay` 类型流水，并更新 `rebate_balances`。
- 实现里程碑阈值判断：未超过 `milestone.amount * milestone.max_times` 的部分不走正常衰减返利。
- 实现跨额处理：首充 250 元时，里程碑覆盖 200 元，衰减返利只计算剩余 50 元。
- 新增 `DecayRebateTest`，覆盖 3 级分配、总额守恒、直接上级最大、幂等、无上级和 250 元跨额场景。
- 更新 Rebate 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：27 个测试，247 个断言。
- `php artisan route:list --path=api/v1` 仍为 10 个接口；B7 当前只落服务层入口。

限制：

- 自动队列消费待后续接入。
- 用户端/后台返利流水查询待后续页面和 API 接入。

## 2026-06-13：落地 B6 里程碑奖励服务层

目标：

- 消费 B5 明确充值事件，根据 B3 配置和 B4 直接上级链路发放新人里程碑奖励。
- 实现默认规则：每满 100 元奖励直接上级 15 元，最多 2 次，单次 250 元只触发 2 次。

完成：

- 新增 `user_rebate_progress` 表，记录新人累计充值和已触发里程碑次数。
- 新增 `rebate_balances` 表，记录用户独立返利余额。
- 新增 `rebate_records` 表，记录返利流水。
- 新增 `UserRebateProgress`、`RebateBalance`、`RebateRecord` 模型。
- 新增 `RebateBalanceService`，支持增加可提现返利余额。
- 新增 `MilestoneService`，处理充值事件、判断可触发里程碑次数、写返利流水、更新余额、标记事件 processed。
- 新增 `MilestoneTest`，覆盖 100 元、累计 200 元、单次 250 元、重复事件和无上级场景。
- 更新 Milestone/Rebate 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：22 个测试，214 个断言。
- `php artisan route:list --path=api/v1` 仍为 10 个接口；B6 当前只落服务层入口。

实现口径：

- 单个充值事件跨多个里程碑时，当前写一条汇总 `rebate_record`，例如 250 元触发 2 次时 `rebate_amount = 30`，配置快照记录 `milestone.triggered_times = 2`。
- 无上级用户不会发放里程碑奖励，也不消耗里程碑奖励次数。

## 2026-06-13：落地 B5 充值事件服务层入口

目标：

- 建立明确、幂等、可追溯的充值事件入口。
- 避免后续返利引擎基于 Sub2API `users.balance` 差值发放返利。

完成：

- 新增 `payment_records` 表，用于记录充值/支付来源、原始金额、标准金额、额度金额和配置快照。
- 新增 `rebate_events` 表，用于后续 B6/B7 返利引擎消费。
- 新增 `PaymentRecord`、`RebateEvent` 模型。
- 新增 `RechargeEventService`，支持服务层创建充值事件。
- 新增管理员手动补录服务方法，要求管理员角色和备注。
- 实现 `source_type + source_id` 幂等处理，同一来源重复请求不会重复创建事件。
- 金额换算读取 `payment.cny_to_credit_rate`，并写入事件配置快照。
- 新增 `RechargeEventTest`，覆盖管理员补录、幂等、普通用户拒绝、备注必填和异常数据拒绝。
- 更新 Payment 模块文档、实施计划和项目状态。

验证：

- `php artisan test` 通过：17 个测试，191 个断言。
- `php artisan route:list --path=api/v1` 仍为 10 个接口；B5 当前只落服务层入口，未开放后台 API。

限制：

- 后台补录页面/API、审计日志和 Sub2API 扫描器待 B10/B11 接入。

## 2026-06-13：落地 B4 邀请绑定和树状层级后端基础

目标：

- 实现 Sub2Rebate 自己的多级邀请关系，不依赖 Sub2API 原生 affiliate 作为主分销树。
- 为后续里程碑奖励和多级返利提供可查询的上级链路。

完成：

- 新增 Invite 模块服务、控制器和 Provider。
- 新增 `GET /api/v1/invite/me`：返回 Sub2Rebate 邀请码/链接、Sub2API 原生 aff 链接、上级、层级和邀请统计。
- 新增 `POST /api/v1/invite/bind`：支持邀请码绑定，写入 `parent_user_id`、`path`、`depth`。
- 新增 `GET /api/v1/invite/tree`：以当前用户为根节点查看下级树，支持 `maxDepth`。
- 新增 `GET /api/v1/invite/records`：分页查看下级邀请记录。
- 新增 `InviteService::ancestorIds()`，供后续 B6/B7 查询直接上级和多级上级链。
- 新增 `InviteTest`，覆盖 A -> B -> C 链路、树、记录、自邀请、重复绑定和未登录拦截。
- 更新 API 契约、实施计划、项目状态和 Invite 模块文档。

验证：

- `php artisan test` 通过：13 个测试，176 个断言。
- `php artisan route:list --path=api/v1` 可列出 10 个接口。

限制：

- 邀请记录里的累计充值和累计返利金额当前返回 `0.00`，等待 B5/B6/B7 接入真实充值事件和返利流水。
- 管理员修正邀请关系放到 B10 后台阶段。

## 2026-06-13：落地 B3 配置中心后端读取基础

目标：

- 让金额换算、里程碑、返利、提现和风控默认参数从配置中心读取。
- 避免后续 B5/B6/B8 在业务代码里写死关键金额参数。

完成：

- 新增 `config_items` 迁移。
- 新增默认配置清单，覆盖 milestone、rebate、payment、withdraw、risk。
- 新增 `ConfigService`，支持默认配置补齐、缓存读取、dot key 获取。
- 新增 `GET /api/v1/config/items`，返回配置项、Tips 和嵌套 `values`。
- 新增 `ConfigItemSeeder` 并挂入 `DatabaseSeeder`。
- 新增 `ConfigItemsTest`，覆盖默认配置读取、服务读取和未登录拦截。
- 更新 API 契约、实施计划、项目状态和 Config 模块文档。

验证：

- `php artisan test` 通过：9 个测试，140 个断言。
- `php artisan route:list --path=api/v1` 可列出 6 个接口。

限制：

- 当前只做配置读取。
- Filament 配置页面、配置修改审计、管理员备注和严格范围校验在 B10 后台阶段继续实现。

## 2026-06-13：确认 Sub2API 上游账号监控进入第一版

目标：

- 把 `admin/accounts` 上游模型账号/渠道账号监控纳入第一版后台范围。
- 避免继续把 Sub2API `accounts` 与用户账号体系混用。

完成：

- 更新实施计划：新增 B12「Sub2API 上游账号监控」任务。
- 更新项目状态、Sub2API 集成模块、Admin 模块和 API 契约后台资源口径。
- 明确 B11 只负责 Sub2API 充值、兑换、支付审计事件扫描；B12 负责 Admin API 上游账号运营看板。

确认口径：

- `admin/users` / `users` / `auth_identities` 才是用户账号体系。
- `admin/accounts` 是上游模型账号/渠道账号，用于状态、用量、统计监控。
- B12 第一版必须做，但不参与返利发放、充值事件识别和用户余额计算。
- Admin API 使用 `x-api-key`，不能用 Sub2API 管理网页登录密码做自动化凭据。

## 2026-06-13：确认充值事件、安全权限和金额配置口径

目标：

- 明确 Sub2API 兑换码、手动加额度、余额减少和返利事件之间的安全边界。
- 避免后续开发把 `users.balance` 差值当成自动返利依据。

完成：

- 新增 ADR-012：充值返利事件必须来自明确充值来源，余额快照只做对账。
- 新增 ADR-013：金额口径和兑换比例后台可配置。
- 新增 ADR-014：管理权限优先跟随 Sub2API 角色，并允许本系统补充限制。
- 更新实施计划：B3/B5/B6/B8/B10/B11 对齐金额换算、跨额里程碑、提现配置、后台权限和 Sub2API 扫描规则。
- 更新 Payment、Config、Withdraw、Admin、Milestone、Integration 模块文档。
- 更新 API 契约：补充金额安全、管理员权限、幂等键和敏感操作备注要求。

确认口径：

- 余额会随消费持续减少，`users.balance` / `users.total_recharged` 不能直接自动触发返利。
- 手动加额度应尽量走兑换码/订单/Sub2Rebate 后台补录入口。
- 默认 100 元一个里程碑、最多 2 次，单次充值 250 元也只触发 2 次。
- 当前默认 1 人民币 = 1 Sub2API 额度/刀，但必须后台可配置。
- 支付宝自动充值/自动提现第一版先空着。

补充确认：

- 暂不禁止管理员直接在 Sub2API 改余额；但这类变更只做对账和人工确认，不自动发放返利。
- 提现每日次数默认 1 次，可后台调整。
- 返利冻结天数第一版默认 0 天；含义是返利确认后是否需要等待 N 天才能提现。
- Sub2API 上游账号监控指 `admin/accounts` 模型账号/渠道账号用量看板，不是用户账号；后续已确认进入第一版后台范围。

## 2026-06-13：沉淀 Sub2API 只读连接 Skill

目标：

- 将“从自有服务器 Sub2API 获取数据”的安全流程沉淀为可复用 Codex skill。
- 以后部署其他服务器时，复用同一套创建只读库账号、SSH 隧道、Laravel `.env` 和验证步骤。

完成：

- 新增用户级 Codex skill：`/Users/macbook/.codex/skills/sub2api-readonly-link`。
- Skill 覆盖 PostgreSQL 只读角色创建/轮换、`users` / `user_affiliates` / 充值事件表安全读取、SSH 隧道、本地与同 Docker 网络部署配置。
- Skill 未写入任何真实服务器密码、数据库密码、API Key 或用户敏感数据。

验证：

- 已人工检查 skill frontmatter、`agents/openai.yaml` 和参考命令文件结构。
- `quick_validate.py` 因本机 Python 缺少 `PyYAML` 未运行成功；文件结构已按 skill 模板生成并安装。

## 2026-06-13：安装本地 PHP/Composer 工具链

目标：

- 在本机没有系统 `php` / `composer` 的情况下，给项目准备可用的后端运行工具链。
- 避开当前 Homebrew 过旧、tap 异常和源码编译依赖失败的问题。

完成：

- 使用 micromamba 在项目内 `.tools/conda/php` 安装 PHP 8.5.7。
- 安装 Composer stable 2.10.1，入口为 `.tools/conda/php/bin/composer`。
- `.gitignore` 增加 `/.tools/`，避免本地工具链进入版本库。
- 后端 `composer.json` 增加 `config.platform.php = 8.2.0`，避免在 PHP 8.5 本机解析出只支持 PHP 8.4+ 的依赖。
- 重新生成 `backend/composer.lock`，确认 lock 中不再残留 PHP 8.4+ 依赖要求。
- 修正 `backend/bootstrap/app.php` 中多余的 `use Throwable;` warning。
- `composer install --no-dev` 已完成，Laravel 运行时依赖可用，package discovery 通过。
- 移除 `laravel/pint` dev 依赖，避免格式化工具下载失败阻塞 B1/B2 测试验证。
- 完整 `composer install` 已完成，PHPUnit dev 依赖可用。
- 修正 API 未登录时尝试重定向 `login` 路由导致 500 的问题，改为返回统一 401 JSON。
- 新增 `backend/.env.testing`，避免测试时缺少 `.env` 产生 dotenv warning。

验证：

- `.tools/conda/php/bin/php -v` 返回 PHP 8.5.7。
- `.tools/conda/php/bin/composer --version` 返回 Composer 2.10.1。
- `find app bootstrap config database routes tests public -name '*.php' -exec ../.tools/conda/php/bin/php -l {} +` 在 `backend/` 通过。
- `COMPOSER_HOME=/Users/macbook/Desktop/分销/.tools/composer/home COMPOSER_CACHE_DIR=/Users/macbook/Desktop/分销/.tools/composer/cache ../.tools/conda/php/bin/composer validate --no-check-publish` 在 `backend/` 通过。
- `php artisan route:list --path=api/v1` 可列出 5 个接口。
- 临时 sqlite 迁移通过。
- 服务层 smoke 通过：Sub2API fake 用户登录、本地用户同步、Sanctum token 入库、`sub2api_aff_code` / `sub2api_inviter_id` 快照、资料接口 Sub2Rebate 邀请链接和 Sub2API 原生邀请链接均正常。
- HTTP smoke 通过：`GET /api/v1/health` 返回 200，未登录 `GET /api/v1/auth/me` 返回统一 401 JSON。
- `php artisan test` 通过：6 个测试，32 个断言。

限制：

- 本机 PHP 8.5 高于 `composer.json` 的目标平台 PHP 8.2，`composer check-platform-reqs` 会因 `openspout` 不支持 PHP 8.5 失败；部署/CI 应使用 PHP 8.2/8.3/8.4，或后续调整依赖。

下一步：

- 创建 Sub2Rebate 专用 Sub2API 只读库账号并填入 `.env`。

## 2026-06-13：落地 B1/B2 后端模块与 Sub2API 登录适配

目标：

- 建立 Laravel 后端模块基础结构。
- 实现 Sub2API 用户只读读取和密码校验。
- 使用 Sub2Rebate 自己的 Sanctum token 维护登录态。
- 聚合 Sub2API 原生 `user_affiliates.aff_code` 到用户资料接口。

完成：

- 新增 `backend/app/Modules/*` 模块目录和 `ModuleServiceProvider`。
- 新增基础错误码 `App\Support\ApiError`，补充 API 认证/异常统一 JSON 响应。
- 新增本地 `App\Models\User`，本地用户 ID 直接复用 Sub2API `users.id`，并保存 `sub2api_aff_code` / `sub2api_inviter_id` 快照。
- 新增迁移：`users`、`personal_access_tokens`、`sub2_user_roles`、`referral_paths`。
- 新增 `Sub2ApiUserRepository`，通过 `sub2api` 只读连接读取 `users` 并左联 `user_affiliates`。
- 新增 `Sub2RebateAuthService`，完成 Sub2API bcrypt 密码校验、本地用户同步和 Sanctum token 签发。
- 新增 `AccountProfileService`，聚合用户资料、Sub2Rebate 邀请码、Sub2API 原生 aff code / 邀请链接。
- 接入接口：`POST /api/v1/auth/login`、`POST /api/v1/auth/logout`、`GET /api/v1/auth/me`、`GET /api/v1/account/profile`。
- 补充 `config/auth.php`、`config/hashing.php` 和 `config/sub2rebate.php`。
- 前端账户页已改为只展示 Sub2API 原生邀请码和邀请链接。
- 修正邀请码生成失败时的异常抛出，避免手动构造 Laravel `QueryException`。
- 修正 `auth:sanctum` 未登录默认重定向导致 API 500 的问题。
- 新增 `AuthAndProfileTest`，覆盖登录同步用户、token 签发、资料接口邀请链接聚合、错误密码、禁用用户、未登录 401。
- 新增 `.env.testing`，测试环境默认使用 sqlite memory、array cache/session 和 sync queue。

限制：

- Filament 管理员登录策略尚未落地，建议并入 B10 后台阶段处理。
- `account/profile` 的返利余额先返回 0，占位等待后续 Rebate/Withdraw 模块接入真实余额。

验证：

- `PATH=/Users/macbook/.nvm/versions/node/v22.22.0/bin:$PATH npm run build` 在 `frontend/` 通过。
- PHP 语法检查通过。
- 后端路由列表、迁移、服务层 smoke 和 HTTP smoke 均通过。
- `php artisan test` 通过：6 个测试，32 个断言。
- 默认 `npm run build` 仍会命中 Node 14 并失败，需切到 Node 18+。

影响模块：

- Backend、Sub2API Integration、User Center、Invite、Frontend Account。

下一步：

- 创建 Sub2Rebate 专用 Sub2API 只读库账号并填入 `.env`。
- 继续推进 B3 配置中心或 B4 邀请绑定/树状层级。

## 2026-06-13：完成 Sub2API 集成可行性调研

目标：

- 根据服务器记录核验当前 Sub2API 公网入口、源码、接口和文档。
- 明确共享数据库、用户账号共用、上游账号用量监控和登录鉴权方案。
- 判断 Sub2Rebate 第一版如何从 Sub2API 获取充值事件。

完成：

- 新增 `docs/SUB2API_INTEGRATION_RESEARCH.md`。
- 核验 `https://api.sjiaa.cc.cd/` 和 `http://154.44.9.60:8080/` 当前返回 `HTTP 200`。
- 重新 clone `Wei-Shaw/sub2api` 源码并确认当前版本 `e34ad2b`。
- 确认 Admin API 推荐 `x-api-key`，上游账号列表和上游账号用量接口可用于运营监控。
- 确认登录接口使用 `email` / `password`，响应包含 `access_token`、`refresh_token`、`expires_in`。
- 确认共享数据库方案可行，但事件源应优先使用 `payment_orders`、`redeem_codes`、`payment_audit_logs`。
- 直接 SSH 到生产服务器做只读核验：`admin/users` 才是用户账号体系，`admin/accounts` 是上游模型账号/渠道账号。
- 确认生产库 `auth_identities` 当前只有 `email` provider，第一版可按邮箱密码共用 Sub2API 用户。
- 确认当前生产库 `payment_orders` 和 `payment_audit_logs` 为空，`redeem_codes` 已有使用记录，因此当前优先从 `redeem_codes` 接充值事件。
- 按用户要求开启 Sub2API 原生 affiliate：写入 `affiliate_enabled = true` 和默认返利参数，现有 2 个用户补齐 `user_affiliates` 档案。
- 验证 `settings/public` 返回 `affiliate_enabled: true`，`admin/affiliates/users/{id}/overview` 正常，`/affiliate` 返回 `HTTP 200`。
- 修正 Integration 模块、ADR、实施计划和项目状态中“直接读取额度变动表/只监控余额”的旧表述。

限制：

- 正式开发前仍建议创建 Sub2Rebate 专用 PostgreSQL 只读账号，不要用生产库主账号连接。
- 账号监控器仓库本次重新 clone 需要 GitHub 凭据，正式结论以 Sub2API 主源码和服务器记录为主。

影响模块：

- Sub2API Integration、User Center、Payment、Rebate、Audit。

下一步：

- 创建 Sub2Rebate 专用 PostgreSQL 只读账号。
- 继续按计划推进 S2、B1、F1；B11 开发时按新事件源实现扫描器。

## 2026-06-13：创建 Vue 前端 F0 骨架

目标：

- 创建 `frontend/` Vue 用户前端骨架。
- 接入 Vite、TypeScript、Element Plus、Pinia、Vue Router、Axios、Tailwind CSS。
- 按 `docs/API_CONTRACT.md` 准备 API client、类型、Mock 入口和基础页面。

完成：

- 新增 `frontend/` 目录和 Vite + Vue 3 + TypeScript 基础工程。
- 新增基础路由、登录布局、用户中心布局、顶部栏、侧边导航和占位页面。
- 新增 `src/api/`、`src/types/`、`src/utils/`、`src/stores/`、`src/mocks/` 初始结构。
- 新增设计 token、Element Plus 覆盖和 Tailwind 配置。
- 更新 `docs/modules/user-center/README.md` 和 `docs/PROJECT_STATE.md`。

验证：

- `PATH=/Users/macbook/.nvm/versions/node/v22.22.0/bin:$PATH npm run build` 通过。
- 默认 `npm run build` 会命中 `/usr/local/bin/node` v14.18.0 并失败，后续前端命令需先切到 Node 18+。

影响模块：

- User Center、前端骨架。

目录变动：

- 新增 `frontend/`。

下一步：

- 推进 S2：按 `docs/API_CONTRACT.md` 补全 Mock 数据。
- 推进 F1：细化设计 Token 和基础布局。

## 2026-06-13：创建 Laravel 后端 B0 骨架

目标：

- 创建 `backend/` Laravel 后端骨架。
- 准备 PostgreSQL、Redis、Sanctum、Filament 依赖入口。
- 提供基础健康检查接口和测试。

完成：

- 新增 `backend/` 目录，按 Laravel 12 结构准备启动文件、配置、路由、测试和 README。
- 新增 `GET /api/v1/health`，响应字段对齐 `docs/API_CONTRACT.md`。
- 新增 `App\Support\ApiResponse`，供后续 API 复用。
- `.env.example` 增加 PostgreSQL、Redis、Sub2API 只读库和 Sanctum 相关配置。
- 因当时环境没有 `php` 和 `composer`，未安装依赖，`php artisan test` 需要后续在 PHP/Composer 环境验证。

影响模块：

- Backend、公共后端骨架。

目录变动：

- 新增 `backend/`。

下一步：

- 在可用 PHP/Composer 环境执行 `composer install`、`php artisan key:generate`、`php artisan test`。
- 继续推进 B1：模块基础结构、模块 ServiceProvider、错误码和更完整的统一响应约定。

## 2026-06-13：完成 S1 API 契约文档

目标：

- 为后端 Laravel API 和前端 Vue Mock/API client 提供统一接口契约。
- 明确统一响应、认证方式、分页、金额格式、错误码和状态枚举。
- 固定第一版用户端 API 和后台资源口径。

完成：

- 新增 `docs/API_CONTRACT.md`。
- 契约覆盖健康检查、认证、仪表盘、邀请、推广、返利、提现、账户资料接口。
- 明确后台第一版优先使用 Filament Resource，如需自定义后台 API 必须先补契约。
- 修正根 `README.md`、`docs/IMPLEMENTATION_PLAN.md`、`docs/AI_CONTEXT.md` 中 Laravel / Filament 版本残留，统一为 Laravel 12 + Filament 3。
- 更新 `docs/README.md` 文档中心索引。
- 更新 `docs/PROJECT_STATE.md`，将下一步推进到 B0 / F0 骨架。

影响模块：

- 公共契约、User Center、Invite、Rebate、Withdraw、Admin、后端骨架、前端骨架。

目录变动：

- 新增 `docs/API_CONTRACT.md`。

下一步：

- 完成 B0：Laravel 后端骨架。
- 完成 F0：Vue 前端骨架。
- 前端 S2 Mock 数据按 `docs/API_CONTRACT.md` 字段落地。

## 2026-06-13：确认技术栈版本、集成方式和提现门槛

目标：

- 确认 Laravel 和 Filament 的实际可用版本号。
- 确认 Sub2API 额度变化的监听方式。
- 确认提现最低金额。

完成：

- ADR-009：技术栈确认为 Laravel 12 + Filament 3。
- ADR-010：Sub2API 集成采用共享数据库方式，定时任务扫描额度变动。
- ADR-011：提现最低金额 50 元，可配置。
- 更新 `docs/PROJECT_STATE.md`、`docs/README.md` 中技术栈版本号。
- 更新 `docs/IMPLEMENTATION_PLAN.md` 待确认项，移除已确认条目。
- `docs/PROJECT_STATE.md` 待确认列表清空。

影响模块：

- 全局文档、Integration、Withdraw、Config。

下一步：

- 开始执行 S1（API 契约）→ B0（后端骨架）→ F0（前端骨架）。

## 2026-06-13：落地完整实施开发计划

目标：

- 根据当前项目文档和 UI 效果稿，制定可直接执行的完整开发计划。
- 将任务按公共契约、后端、前端、联调拆分，避免前后端互相干扰。
- 明确前端和后端的架构目录规则、任务顺序、验收标准和失败重启规则。

完成：

- 新增 `docs/IMPLEMENTATION_PLAN.md`。
- 计划中补充前端目录结构、后端模块目录结构、数据表计划、API 契约任务、UI 效果稿映射。
- 计划中明确 P0/P1 页面、后端 B0-B11 任务、前端 F0-F8 任务、联调 I0-I2 任务。
- 更新 `docs/README.md` 文档中心索引。
- 更新 `docs/PROJECT_STATE.md` 的下一步建议动作。

影响模块：

- 全项目文档、User Center、Admin、Invite、Milestone、Rebate、Withdraw、Payment、Config、Risk、Audit、Sub2API Integration。

目录变动：

- 新增 `docs/IMPLEMENTATION_PLAN.md`。

下一步：

- 创建 `docs/API_CONTRACT.md`。
- 创建 `backend/` Laravel 骨架和 `frontend/` Vue 骨架。
- 按实施计划任务编号推进。

## 2026-06-14：管理端敏感调额、Sub2API 邀请链路与退出修复

目标：

- 管理端余额调整二次校验管理员密码，金额只能输入数字，默认备注为“返利金额调整”。
- API 额度管理从前端 mock 改为真实调用 Sub2API Admin API，默认原因“充值”、备注“余额充值”。
- 用户端不再展示 Sub2Rebate 自有分销邀请码，改为展示 Sub2API 原生邀请码/邀请链接。
- 登录同步时按 Sub2API `user_affiliates.inviter_id` 写入本地 `referral_paths`，返利计算读取已同步本地层级。
- 修复管理端退出后缓存状态残留，并移除后台侧边栏“切换到用户端”入口。

完成：

- `AdminBalanceController::adjust` 增加 `adminPassword` 校验，校验通过后才调整 Sub2Rebate 返利余额。
- 新增 `POST /api/v1/admin/users/{id}/api-quota`，调用 Sub2API `POST /api/v1/admin/users/{id}/balance`，并在充值时创建本地充值/返利事件。
- `Sub2ApiAdminClient` 增加用户详情、余额调整、余额历史方法。
- `InviteService::syncFromSub2Api` 根据 Sub2API 邀请人同步本地层级；登录和资料页刷新都会触发同步。
- 用户端推广中心和账户设置页展示 Sub2API 邀请链接，隐藏 Sub2Rebate 自有邀请码。
- 用户端“我的推荐关系”改为调用真实 `GET /invite/tree`。
- 管理端退出清理 token、用户态和 sessionStorage，并重置路由鉴权初始化状态。

验证：

- 后端全量测试通过：63 个测试，422 个断言。
- 前端 `vue-tsc --noEmit` 通过。
- 前端 `vite build` 通过；仅保留 Element Plus / chunk size 既有 warning。

注意：

- 明文测试密码、服务器信息和 Sub2API 管理凭据不写入文档、代码或 Git。

## 2026-06-13：确认核心算法参数 + 补充 Agents 规范

目标：

- 确认里程碑、多级分发、层级策略。
- 补充 AI Agents 全局规范。
- 补充 API 契约、并发、测试规范。
- 确认用户认证方案。
- 确认额度来源触发返利策略。

完成：

- ADR-005：Sub2API 手动加额度也需触发返利。
- ADR-006：复用 Sub2API 账号，区分管理员和普通用户。
- ADR-007：衰减系数模式，无限级金字塔结构。
- ADR-008：里程碑奖励次数默认 2 次。
- 新增 `docs/AGENTS.md` — AI 全局行为规范。
- 新增 PROJECT_GUIDELINES 第 9-11 节（API 契约、并发幂等、测试规范）。
- 更新 Rebate 模块文档：从固定比例改为衰减系数，无限级。
- 更新 Milestone 模块文档：奖励次数从 3 改为 2。
- 更新 Config 模块文档：完整配置项列表。
- 更新 User Center、Admin 模块文档：认证方案。
- 更新 Integration 模块文档：两个额度来源都触发返利。
- 更新 ARCHITECTURE.md：第一版做衰减系数而非固定比例。
- 全局一致性检查：消除所有"5 级"、"固定比例"、"max_level"残留。

影响模块：

- Rebate、Milestone、Config、User Center、Admin、Integration、全局文档。

目录变动：

- 新增 `docs/AGENTS.md`。

下一步：

- 确认衰减系数 0.4 和返利池比例 15% 是否合适。
- 确认提现最低金额。
- 准备创建项目代码骨架（用户确认后）。

## 2026-06-13：建立项目文档规范

目标：

- 建立项目长期文档体系。
- 确保后续开发不会因为上下文压缩或记忆丢失而失焦。

完成：

- 新建 `docs/` 文档中心。
- 新建项目规范、架构、路线图、开发流程、项目状态、决策记录、AI 上下文。
- 新建各核心模块文档。

影响模块：

- 全项目。

下一步：

- 根据这些规范创建实际项目目录。
- 确认 Laravel 后端与 Vue 前端的仓库结构。
