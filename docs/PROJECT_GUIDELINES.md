# 项目规范

## 1. 项目定位

Sub2Rebate 是一个独立分销返利系统，用于补足 Sub2API 原生邀请返利能力不足的问题。

项目必须保持以下边界：

- 不直接修改 Sub2API 核心代码作为第一方案。
- 返利系统独立部署、独立维护、独立配置。
- 返利余额与 Sub2API 用户额度分离。
- 所有返利、提现、配置变更必须可追溯。

## 2. 第一版目标

第一版只做最小可用闭环：

```text
用户绑定邀请关系
-> 被邀请人充值累计触发里程碑
-> 直接上级获得里程碑奖励
-> 里程碑结束后进入多级返利
-> 返利进入独立余额
-> 用户提交提现
-> 管理员人工审核提现
-> 完整流水和审计
```

第一版不追求复杂，但必须稳定、清晰、可对账。

## 3. 前端技术栈

| 项 | 选择 | 说明 |
|---|---|---|
| 框架 | Vue 3 + TypeScript | Composition API |
| 构建 | Vite | |
| CSS | Tailwind CSS | 主样式，对齐设计稿 Design Token |
| UI 组件库 | Element Plus | 按需引入，用于表格/表单/弹窗/日期选择等功能型组件 |
| 图标 | Material Symbols Outlined | Google Fonts 引入 |
| 字体 | Inter | 400/500/600/700 |
| 图表 | ECharts | 折线图/柱状图/饼图等数据看板 |
| 关系图/树图 | AntV G6 | 邀请树可视化、返利链路图 |
| 状态管理 | Pinia | |
| 路由 | Vue Router 4 | |
| HTTP | Axios | 拦截器统一处理 token 和错误 |
| 测试 | Vitest | |
| 适配 | 响应式（移动端 + PC） | |
| 语言 | 仅中文 | |

### 3.1 设计 Token

原型设计系统基于 Material Design 3 色彩体系：

```text
主色 primary: #000000
强调色 secondary: #4648d4
成功色 rebate-success: #10B981
错误色 error-destructive: #EF4444
背景 background: #f7f9fb
卡片白 surface-white: #FFFFFF
边框 border-subtle: #E2E8F0
辅助文字 text-muted: #64748B
```

Tailwind 配置中扩展这些 Token，Element Plus 主题变量覆盖对齐。

## 4. 代码组织原则

后端按业务模块组织，避免所有逻辑堆在 Controller 或 Model 中。

推荐后端模块边界：

```text
app/
├── Modules/
│   ├── Invite/
│   ├── Milestone/
│   ├── Rebate/
│   ├── Withdraw/
│   ├── Payment/
│   ├── Config/
│   ├── Risk/
│   ├── Audit/
│   └── Sub2Api/
```

每个模块内部建议：

```text
ModuleName/
├── Actions/       单一业务动作
├── Services/      业务服务
├── Models/        领域模型
├── DTOs/          数据传输对象
├── Enums/         状态枚举
├── Policies/      权限策略
├── Jobs/          队列任务
├── Events/        事件
├── Listeners/     监听器
└── README.md      模块说明，代码内可选，docs 内必须有
```

前端按业务页面和可复用能力分层：

```text
frontend/
├── src/
│   ├── api/           接口封装
│   ├── assets/        静态资源
│   ├── components/    通用组件
│   ├── layouts/       页面布局
│   ├── router/        路由
│   ├── stores/        Pinia 状态
│   ├── styles/        全局样式
│   ├── utils/         工具函数
│   └── views/         页面
```

## 5. 技术选型原则：优先搜索现有轮子

开发中遇到任何通用需求时，必须遵循以下顺序：

### 5.1 决策流程

```text
遇到需求
-> 先搜索是否有成熟的 npm 包 / 插件 / 组件库
-> 评估：Star 数、维护活跃度、包体积、与当前技术栈兼容性
-> 有合适轮子 -> 直接用，不重复造
-> 没有 / 不满足 -> 才自己实现
```

### 5.2 必须搜索的场景

- UI 组件：日期范围选择、级联选择器、虚拟滚动表格 → 先看 Element Plus 有没有
- 图表：折线/柱状/饼图 → ECharts
- 树图/关系图/流程图 → AntV G6
- 表单校验 → VeeValidate / Element Plus 内置
- 复制到剪贴板 → vue-clipboard3 / @vueuse/core 的 useClipboard
- 二维码生成 → qrcode / vue-qrcode
- 文件导出 → xlsx / file-saver
- 动画/过渡 → @vueuse/motion / Tailwind 动画类
- 工具函数 → @vueuse/core（优先）/ lodash-es
- 移动端手势 → @vueuse/gesture
- 无限滚动/虚拟列表 → @tanstack/vue-virtual
- HTTP 请求取消/重试 → axios-retry
- 日期处理 → dayjs

### 5.3 禁止事项

- 禁止手写已有成熟实现的功能（如自己写复制到剪贴板、自己写二维码生成）。
- 禁止为了"减少依赖"而花大量时间手写组件，除非现有方案确实不满足需求。
- 禁止引入已停止维护的包（最后更新超过 1 年 + 大量未关闭 issue）。
- 禁止引入功能重叠的同类包（如同时用 moment 和 dayjs）。

### 5.4 引入新依赖流程

引入新的 npm 包前需确认：

1. 包名正确，不是 typosquatting。
2. 周下载量 > 1000 或为知名组织维护。
3. 最近 6 个月内有更新。
4. 使用固定版本号（非 ^ 或 ~），锁定 lock 文件。
5. 记录在开发日志中说明引入原因。

## 6. 命名规范

### 6.1 后端

- 类名使用 PascalCase。
- 方法名使用 camelCase。
- 数据库表使用 snake_case 复数形式。
- 枚举值使用清晰业务名，不使用魔法数字。
- 金额字段统一使用 decimal，不使用 float。

示例：

```text
rebate_records
withdraw_records
referral_paths
rebate_balances
```

### 6.2 前端

- Vue 组件使用 PascalCase。
- 页面目录使用 kebab-case。
- API 文件按模块命名。

示例：

```text
views/promotion/PromotionDashboard.vue
api/rebate.ts
stores/user.ts
```

## 7. 金额处理规范

金额是系统核心，必须严格处理：

- 数据库存储 decimal。
- 后端计算使用高精度 decimal 库或数据库 decimal。
- 不使用 JavaScript float 作为最终金额依据。
- 前端只负责展示，不负责关键金额计算。
- 所有金额计算结果必须写入流水。

## 8. 状态机规范

提现、返利、支付都必须有明确状态。

提现状态示例：

```text
pending     待审核
approved    已审核
paid        已打款
rejected    已拒绝
failed      打款失败
canceled    已取消
```

返利状态示例：

```text
pending     待确认
confirmed   已确认
frozen      冻结中
available   可提现
canceled    已取消
```

支付状态示例：

```text
created     已创建
paid        已支付
failed      支付失败
closed      已关闭
refunded    已退款
```

## 9. 审计规范

以下行为必须记录：

- 返利发放。
- 返利取消。
- 提现申请。
- 提现审核。
- 提现打款。
- 配置修改。
- 管理员手动调整余额。
- 邀请关系手动修正。

审计记录至少包含：

```text
操作人
操作对象
操作类型
变更前
变更后
操作时间
IP / User Agent
备注
```

## 10. 文档规范

每次开发必须做到：

- 改需求，更新需求文档。
- 改架构，更新架构文档。
- 改模块，更新模块文档。
- 做完功能，更新开发日志。
- 出现重要选择，更新决策记录。

未经文档记录的功能，视为未完成。

## 11. API 契约规范

### 11.1 路由前缀

所有后端 API 使用统一前缀：

```text
/api/v1/
```

后续有大版本不兼容变更时使用 `/api/v2/`。

### 11.2 响应格式

统一 JSON 响应结构：

```json
{
  "code": 0,
  "message": "ok",
  "data": {}
}
```

错误时：

```json
{
  "code": 40001,
  "message": "余额不足",
  "data": null
}
```

### 11.3 错误码规则

| 码段 | 含义 |
|---|---|
| 0 | 成功 |
| 40000-40099 | 通用参数错误 |
| 40100-40199 | 认证与权限 |
| 41000-41099 | Invite 模块 |
| 42000-42099 | Milestone 模块 |
| 43000-43099 | Rebate 模块 |
| 44000-44099 | Withdraw 模块 |
| 45000-45099 | Payment 模块 |
| 46000-46099 | Config 模块 |
| 47000-47099 | Risk 模块 |
| 50000-50099 | 服务端内部错误 |

每个模块在自身文档中维护具体错误码。

### 11.4 HTTP 状态码对照

| HTTP 状态码 | 使用场景 |
|---|---|
| 200 | 成功 |
| 201 | 创建成功 |
| 400 | 参数校验失败 |
| 401 | 未认证 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 422 | 业务规则校验失败（余额不足、超出限制等） |
| 429 | 请求频率超限 |
| 500 | 服务端内部错误 |

## 12. 并发与幂等规范

### 12.1 核心原则

涉及金额变更的操作必须保证并发安全和幂等：

- 一笔充值事件只能触发一次返利。
- 一次提现申请只能冻结一次余额。
- 余额更新必须原子操作。

### 12.2 技术手段

| 场景 | 方案 |
|---|---|
| 返利防重复发放 | rebate_events 表通过唯一约束（source_type + source_id）防止同一事件重复写入 |
| 余额更新 | 使用数据库行级锁（SELECT FOR UPDATE）或原子更新（UPDATE ... WHERE balance >= amount） |
| 提现冻结 | 先锁余额行再扣减，失败回滚 |
| 支付回调 | 幂等键（支付订单号）+ 唯一约束，重复回调不二次处理 |

### 12.3 队列任务幂等

所有影响金额的队列任务必须：

- 在处理前检查事件状态是否已完成。
- 使用数据库事务包裹核心逻辑。
- 失败时不修改已有数据，等待重试或人工处理。

## 13. 测试规范

### 13.1 第一版测试底线

- 金额计算必须有单元测试（里程碑奖励计算、多级返利归一化）。
- 余额变更必须有单元测试（冻结、解冻、扣减）。
- 邀请关系绑定必须有单元测试（循环检测、路径生成）。
- API 端点至少有 Feature Test 覆盖正常流程。

### 13.2 测试工具

```text
后端：PHPUnit（Laravel 自带）
前端：Vitest
```

### 13.3 金额测试要求

金额测试必须覆盖：

- 正常计算。
- 边界值（0 元充值、刚好触发里程碑、最低提现金额）。
- 层级不足时归一化。
- 并发场景（同一事件重复触发）。
