# User Center 模块：Vue 用户中心

## 1. 模块目标

负责用户在前端查看账户、推广、返利、提现等信息。

## 2. 模块边界

本模块负责：

- 登录注册页面。
- 用户中心。
- 推广中心。
- 返利明细。
- 提现申请。
- 支付页面。

本模块不负责：

- 管理后台。
- 后端金额计算。

## 3. 认证方案

```text
用户数据源：Sub2API 用户表
登录验证：读取 Sub2API 用户表校验账号密码
Token 签发：Sub2Rebate 自行签发（Laravel Sanctum）
角色：普通用户（user）
```

用户无需在 Sub2Rebate 单独注册，使用 Sub2API 已有账号直接登录。

当前密码仍由 Sub2API 统一管理，Sub2Rebate 的 `account/change-password` 不修改本地密码，避免登录密码和本地密码不一致。Sanctum token 默认 7 天过期；后台封禁用户会撤销已有 token，登录态接口也会拦截非 active 用户。

## 4. 前端技术

```text
Vue 3 + Vite
Element Plus
Pinia
Vue Router
Axios
```

## 5. 页面规划

```text
/login
/register
/dashboard
/promotion
/promotion/invites
/promotion/rebates
/withdraw
/withdraw/records
/account
/payment
```

## 6. 已完成

- 模块文档初始化。
- F0 前端项目骨架。
- Vite + Vue 3 + TypeScript 基础接入。
- Element Plus、Pinia、Vue Router、Axios、Tailwind CSS 基础接入。
- 基础登录布局、用户中心布局、路由、API 封装、类型、Mock 入口和占位页面。
- 后端已接入 `auth/login`、`auth/logout`、`auth/me`、`account/profile` 首版接口；`account/profile` 已包含 Sub2API 原生 `aff_code` 和邀请链接字段。
- 前端请求拦截器已对并发 401 做跳转防抖，避免多个请求同时触发重复跳转。
- `/withdraw` 小屏布局已修复页面横向溢出，宽表格改为卡片内部横向滚动。

## 7. 待实现

- S2：完整 Mock 数据。
- F1：设计 Token 和基础布局细化。
- F2：API 类型和 Mock 服务完善。
- F3：登录状态和认证流程。
- 用户中心页面。
- 推广中心页面。
- 提现页面。

## 8. 开发记录

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确 Vue 前端范围。
- 下一步：进入 F0 前端项目骨架。

### 2026-06-13

- 目标：完成 F0 Vue 前端骨架。
- 完成：创建 `frontend/`，接入 Vite、Vue 3、TypeScript、Element Plus、Pinia、Vue Router、Axios、Tailwind CSS。
- 验证：使用 Node 22 执行 `npm run build` 通过；默认 PATH 命中 Node 14 时构建失败，需切到 Node 18+。
- 下一步：按 `docs/API_CONTRACT.md` 推进 S2 Mock 数据和 F1 基础布局。

### 2026-06-14

- 目标：修复登录态和移动端提现页体验问题。
- 完成：401 跳转增加防抖；提现页 390px 宽度下不再撑出页面级横向滚动。
- 完成：改密接口文档明确为 Sub2API 统一管理，本地不再制造独立密码。
- 验证：使用 Node 22 执行 `vue-tsc --noEmit` 通过；`vite build` 通过。
