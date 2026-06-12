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

## 7. 待实现

- 前端项目骨架。
- 路由。
- 登录状态。
- 用户中心页面。
- 推广中心页面。
- 提现页面。

## 8. 开发记录

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确 Vue 前端范围。
- 下一步：创建前端项目骨架。

