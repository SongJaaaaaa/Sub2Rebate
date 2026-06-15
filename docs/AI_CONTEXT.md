# AI 交接上下文

> 当上下文压缩、切换会话或隔很久继续开发时，优先阅读本文件。

## 1. 项目是什么

Sub2Rebate 是一个独立分销返利系统，用来配合已部署的 Sub2API。

核心目标：

- 独立维护邀请关系。
- 支持新人累计充值里程碑奖励。
- 支持里程碑结束后的多级返利。
- 支持独立返利余额和提现。
- 后续支持支付宝充值提现、防躺平、活动规则。

## 2. 当前用户明确要求

用户要求：

- 项目要规范。
- 目录结构要清晰。
- 功能封装要明确。
- 每个模块下面都要有文档。
- 每次开发都要补充文档。
- 文档要记录目标和进度。
- 每次新增接口、修改接口、删除接口，都必须同步更新 `docs/API_CONTRACT.md`。
- 避免上下文压缩后不知道做到哪里。

## 3. 当前不要做什么

当前不要：

- 修改 `Sub2Rebate-返利系统深度总结.md`，除非用户明确要求。
- 急着写业务代码。
- 直接上复杂自动支付。
- 第一版开启贡献系数影响金额。

## 4. 当前应该做什么

当前应该：

- 继续按 `docs/IMPLEMENTATION_PLAN.md` 推进开发。
- S1 API 契约已完成，`docs/API_CONTRACT.md` 是唯一接口文档，后续接口变更必须同步更新。
- B0/F0 已创建，B1/B2 已完成首版：Sub2API 用户读取、登录适配、Sanctum token、用户资料和 Sub2API affiliate 链接聚合。
- 下一步优先做 B3 配置中心、B4 邀请绑定/树状层级、B5 安全充值事件入口。
- Sub2API 集成调研已完成源码和公网入口核验，详情见 `docs/SUB2API_INTEGRATION_RESEARCH.md`。
- Sub2API 事件源优先使用 `payment_orders`、`redeem_codes`、`payment_audit_logs`；`users.balance` / `users.total_recharged` 只能用于对账和人工确认，不能直接自动触发返利。
- Sub2API 只读账号 `sub2rebate_ro` 已创建并验证，本地通过 SSH 隧道访问。
- 每次开发更新 docs。

## 5. 第一版核心闭环

```text
邀请关系
-> 邀请树
-> 新人累计充值里程碑奖励
-> 后续多级返利
-> 独立返利余额
-> 人工提现
-> 完整流水
```

## 6. 技术栈

```text
后端：Laravel 12
管理后台：Filament 3
前端：Vue 3 + Vite
UI：Element Plus
状态管理：Pinia
数据库：PostgreSQL
队列：Redis
```

## 7. 每次继续开发时的固定动作

1. 读取 `docs/AI_CONTEXT.md`。
2. 读取 `docs/AGENTS.md`。
3. 读取 `docs/PROJECT_STATE.md`。
4. 读取当前模块文档。
5. 明确本次目标。
6. 开发。
7. 更新模块文档。
8. 更新 `docs/DEVELOPMENT_LOG.md`。
9. 更新 `docs/PROJECT_STATE.md`。
10. 检查目录变动是否需要同步文档。
11. 如涉及接口，更新 `docs/API_CONTRACT.md` 并核对 `php artisan route:list --path=api/v1`。

## 8. 关键约束

- 模块化开发，功能必须封装。
- 目录有变动就更新文档，不首尾不顾。
- 跨模块通过 Event 或 Contract 通信，不直接耦合。
- 金额计算必须有独立计算类和单元测试。
- 无限级金字塔结构，不设层级上限。
- 涉及充值、返利、提现、余额调整的接口必须严谨处理权限、幂等、备注、审计和事务。
- 详细规则见 `docs/AGENTS.md`。
