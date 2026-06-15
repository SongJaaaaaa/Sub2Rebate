# 总体架构

## 1. 架构目标

系统架构需要满足：

- 与 Sub2API 解耦。
- 业务规则可配置。
- 金额计算可追溯。
- 支持树状邀请和多级返利。
- 支持后续支付宝充值提现。
- 支持后续防躺平贡献系数。
- 第一版可快速上线。

## 2. 推荐架构

```text
Vue 3 用户前端
    |
    | HTTP API
    v
Laravel API
    |
    | 业务模块
    v
Invite / Milestone / Rebate / Withdraw / Payment / Config / Risk / Audit
    |
    v
PostgreSQL + Redis
    |
    v
Sub2API 数据库 / Sub2API 接口

Filament 管理后台
    |
    v
Laravel 后端模块
```

## 3. 应用划分

### 3.1 后端

后端使用 Laravel，负责：

- API。
- 业务规则。
- 返利计算。
- 提现审核。
- 队列任务。
- 定时任务。
- 管理后台数据。

### 3.2 管理后台

管理后台使用 Filament，负责：

- 用户管理。
- 邀请关系查看。
- 返利流水查看。
- 提现审核。
- 规则配置。
- 风控名单。
- 数据报表。

### 3.3 用户前端

用户前端使用 Vue 3 + Vite，负责：

- 登录注册。
- 用户中心。
- 推广中心。
- 返利明细。
- 提现申请。
- 支付页面。

## 4. 核心模块关系

```text
Payment 产生充值事件
    |
    v
RebateEvent
    |
    v
Milestone 判断是否处于里程碑阶段
    |
    |-- 是 -> 发放直接上级奖励
    |
    |-- 否 -> Rebate 多级分发
                    |
                    v
             写入 RebateRecords
                    |
                    v
             更新 RebateBalance
                    |
                    v
              Audit 记录审计
```

## 5. 数据流

### 5.1 邀请绑定

```text
推广链接
-> 用户注册
-> 校验邀请码
-> 写 referral_paths
-> 生成用户自己的邀请码
```

### 5.2 返利发放

```text
明确充值事件
-> 创建 rebate_events
-> 判断是否已处理
-> 读取邀请链路
-> 判断里程碑状态
-> 计算返利
-> 写 rebate_records
-> 更新 rebate_balances
-> 标记事件已处理
```

说明：明确充值事件包括 Sub2API `redeem_codes`、`payment_orders`、`payment_audit_logs` 或 Sub2Rebate 后台补录事件。`users.balance` / `users.total_recharged` 快照只做对账和人工确认，不能直接自动触发返利。

### 5.3 提现

```text
用户申请提现
-> 冻结可提现余额
-> 管理员审核
-> 人工或自动打款
-> 更新提现状态
-> 更新余额
-> 写审计日志
```

## 6. 第一版技术边界

第一版做：

- 衰减系数多级返利（无限级金字塔）。
- 里程碑奖励（默认 2 次）。
- 人工提现。
- 配置中心。
- 完整流水。
- 基础风控。

第一版不做：

- 自动支付宝转账。
- 贡献系数自动生效（只记录数据）。
- 多活动并行。
- 复杂等级体系。

## 7. 后续可扩展点

- 支付宝扫码充值。
- 支付宝自动提现。
- 防躺平贡献系数启用。
- 活动返利规则。
- 推广员等级。
- 数据看板。
- 风控模型。
