# Admin 模块：Filament 管理后台

## 1. 模块目标

负责平台运营者管理系统。

## 2. 模块边界

本模块负责：

- 用户管理。
- 邀请关系管理。
- 返利流水。
- 提现审核。
- 配置管理。
- 风控管理。
- 报表查看。
- Sub2API 上游账号监控。

本模块不负责：

- 用户前台。
- 支付宝实际打款逻辑。

## 3. 认证方案

```text
用户数据源：Sub2API 用户表
登录验证：Filament 自带登录，校验 Sub2API 用户表
角色：优先读取 Sub2API users.role = admin
权限标记：Sub2Rebate 维护 sub2_user_roles 作为补充限制和扩展
```

只有 Sub2API 管理员且未被本系统限制的用户可登录 Filament 后台。普通用户不能访问后台或管理 API。

## 4. 后台页面

第一版页面：

- 用户列表。
- 邀请关系。
- 里程碑进度。
- 返利流水。
- 返利余额。
- 提现审核。
- 配置中心。
- 黑名单。
- Sub2API 上游账号监控。

敏感操作要求：

- 配置修改、充值事件补录、余额调整、提现审核必须填写备注。
- 所有敏感操作必须写入审计日志。
- 金额类操作必须在事务中完成。
- 不允许通过普通用户接口执行后台金额操作。

## 5. 已完成

- 模块文档初始化。
- 已确认 Filament 3.3.54 已安装可用。
- 已实现 Filament `AdminPanelProvider`，后台入口为 `/admin`。
- 已实现自定义 Filament 登录页，复用 Sub2API bcrypt 登录适配，不额外签发用户端 Sanctum token。
- 已实现 `User::canAccessPanel()`，普通用户不能访问后台。
- 已实现 `AdminAccessService`，当前按本地同步用户 `role=admin` 且 `status=active` 判断管理员。
- 已实现 `AdminWithdrawService`，支持提现审核通过、拒绝解冻、标记打款。
- 后台敏感操作必须填写备注。
- 管理员封禁用户会撤销该用户已有 Sanctum token。
- 后台余额调整、提现拒绝解冻、标记打款均在事务中锁定余额行。
- 提现审核动作已写审计日志。
- 已补充 `AdminWithdrawTest`，覆盖权限、备注、状态流转、余额解冻/扣减和审计。
- 已实现基础 Filament Resource：用户管理、配置中心、提现审核、返利事件、返利流水、返利余额、里程碑进度、风控标记、审计日志、Sub2API 上游账号监控。
- 已实现配置修改备注和审计。
- 已实现提现审核页面动作：审核通过、拒绝、标记打款。
- 已实现 `User::getFilamentName()`，后台头像/用户菜单使用 `username/email/user_id`。
- 已补充 `FilamentAdminTest`，覆盖后台 Resource 可访问。
- 已实现 Admin API 配置更新基础校验、余额调整二次密码校验和用户封禁 token 失效。

## 6. 待实现

- 邀请关系可视化性能优化：懒加载子树、虚拟渲染。
- 充值事件补录入口。
- 后台首页统计卡片。
- `sub2_user_roles` 对 Sub2API admin 权限的补充限制策略。

## 7. 开发记录

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确管理后台范围。
- 变更：后台权限优先跟随 Sub2API `users.role = admin`，本系统角色表做补充限制。
- 下一步：创建 Filament 登录策略和配置/提现审核 Resource。

### 2026-06-13

- 目标：落地 B10 后台核心动作基础。
- 完成：确认 Filament 包可用；新增管理员权限服务和提现审核服务。
- 完成：提现审核通过、拒绝、标记打款均要求管理员和备注，并写入审计。
- 说明：Filament Resource 页面和管理功能继续在 B10 后续实现。

### 2026-06-13

- 目标：落地 Filament 后台入口和 Sub2API 登录策略。
- 完成：新增 `AdminPanelProvider`、自定义后台登录页和 `FilamentAdminTest`。
- 完成：`/admin/login` 可访问，`/admin` 未登录会跳转登录，后台访问只允许 active admin。
- 说明：Resource 页面和后台运营页面继续实现。

### 2026-06-13

- 目标：收口 B10 基础 Resource 与后台敏感操作入口。
- 完成：新增用户、配置、提现、返利、风控、审计和 Sub2API 上游账号监控 Resource。
- 完成：配置修改要求备注并写审计；提现审核页面接入通过、拒绝、打款动作。
- 验证：`php artisan route:list --path=admin` 可列出 24 个后台路由，`php artisan test` 通过 48 个测试、336 个断言。

### 2026-06-14

- 目标：修复管理端安全与金额一致性风险。
- 完成：封禁用户时撤销已有 token；后台余额调整和提现审核余额变更加行锁。
- 完成：后台配置更新增加关键业务值范围校验；审计日志默认记录 IP 和 User-Agent。
- 验证：后端全量测试通过，141 个测试、668 个断言。
