# Payment 模块：支付与充值

## 1. 模块目标

负责用户充值、支付记录和后续支付宝扫码支付接入。

## 2. 模块边界

本模块负责：

- 创建充值订单。
- 记录支付状态。
- 处理支付回调。
- 生成返利事件。
- 记录外部充值/兑换事件的来源、原始金额、标准金额和换算配置快照。

本模块不负责：

- 返利分发。
- 提现。
- 邀请关系。

## 3. MVP 范围

第一版可以不接入支付宝自动支付，只保留支付记录和充值事件入口。

第一版涉及金额的入口必须先保证：

- 事件有唯一幂等键。
- 后台补录必须要求管理员权限和备注。
- 事件金额保存原始来源金额、标准金额、币种/单位和配置快照。
- 普通用户不能创建或补录充值事件。
- `users.balance` / `users.total_recharged` 快照只能用于对账或人工确认，不能直接自动创建返利事件。

后续阶段接入：

- 支付宝扫码支付。
- 支付宝支付回调。
- 支付对账。

## 4. 状态

```text
created
paid
failed
closed
refunded
```

## 5. 核心流程

```text
创建充值订单
-> 用户支付
-> 支付成功
-> 写 payment_records
-> 创建 rebate_events
```

Sub2API 外部事件入口：

```text
redeem_codes / payment_orders / payment_audit_logs
-> 按 source_type + source_id 幂等入库
-> 换算为标准金额
-> 创建 rebate_events
-> 队列处理里程碑/多级返利
```

管理员手动加额度口径：

```text
优先：通过 Sub2Rebate 后台补录充值事件或统一走兑换码/订单入口
禁止：仅根据 users.balance 差值自动发放返利
```

## 6. 已完成

- 模块文档初始化。
- 已实现 `payment_records` 表。
- 已实现 `rebate_events` 表。
- 已实现 `RechargeEventService` 服务层充值事件入口。
- 已实现管理员手动补录服务方法：要求管理员角色和备注。
- 已实现 `source_type + source_id` 幂等处理。
- 已保存原始金额、标准金额、Sub2API 额度金额和 `payment.cny_to_credit_rate` 配置快照。
- 已注册 Laravel Schedule，每分钟执行 `rebate:process-pending --limit=100` 兜底消费 pending 返利事件。
- 已补充 `RechargeEventTest`，覆盖管理员补录、幂等、普通用户拒绝、备注必填和异常数据拒绝。

## 7. 待实现

- 支付宝接入。
- 回调幂等。
- 对账。
- Sub2API `redeem_codes` / `payment_orders` / `payment_audit_logs` 扫描器接入同一个事件入口。

## 8. 开发记录

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确支付模块边界。
- 变更：明确余额快照不能直接触发返利；手动加额度应进入 Sub2Rebate 事件入口或可追踪订单/兑换码入口。
- 下一步：第一版先定义安全的充值事件入口。

### 2026-06-13

- 目标：落地 B5 充值事件服务层入口。
- 完成：新增 `payment_records`、`rebate_events`、模型、`RechargeEventService` 和测试。
- 完成：管理员手动补录服务层入口会校验管理员角色和备注；普通用户不能创建补录事件。
- 完成：同一 `source_type + source_id` 重复请求不会重复创建事件。
- 说明：后台页面/API 和审计日志等到 B10 后台阶段接入；Sub2API 扫描器在 B11 接入同一服务。

### 2026-06-14

- 目标：避免返利事件只能手动消费。
- 完成：注册 Laravel Schedule，每分钟扫描并派发 pending 返利事件。
- 说明：生产仍建议使用异步队列 worker；`QUEUE_CONNECTION=sync` 会让被派发的 Job 在调度进程内同步执行。
- 验证：后端全量测试通过，141 个测试、668 个断言。
