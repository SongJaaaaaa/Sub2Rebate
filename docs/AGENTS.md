# AI Agents 全局规范

> 本文档定义 AI（Claude / 其他 LLM）参与本项目开发时必须遵守的规则。所有 AI 开发行为都基于此规范执行。

## 1. 核心原则

- 模块化开发，每个模块独立封装，职责单一。
- 功能必须封装，不允许逻辑散落在 Controller 或 Model 中。
- 目录有变动或新增时，必须同步更新文档。
- 文档与代码保持一致，有代码变动就有文档变动。
- 不做首尾不顾的开发，一个功能的代码、测试、文档必须同步完成。

## 2. 目录结构约束

### 2.1 新增目录时必须做

- 在对应的模块 `docs/modules/<module>/README.md` 中更新目录说明。
- 如果是新模块，在 `docs/modules/README.md` 索引中添加条目。
- 更新 `docs/PROJECT_STATE.md` 记录变化。
- 如果影响整体结构，更新 `docs/ARCHITECTURE.md`。

### 2.2 目录结构规则

后端模块新增时必须遵循：

```text
app/Modules/<ModuleName>/
├── Actions/       单一业务动作（一个类做一件事）
├── Services/      业务服务（编排多个 Action）
├── Models/        领域模型
├── DTOs/          数据传输对象
├── Enums/         状态枚举
├── Policies/      权限策略
├── Jobs/          队列任务
├── Events/        事件
├── Listeners/     监听器
├── Exceptions/    模块异常
├── Contracts/     接口定义
└── Tests/         模块测试
```

前端目录新增时必须遵循：

```text
frontend/src/
├── api/           接口封装（按模块拆文件）
├── assets/        静态资源
├── components/    通用组件
├── composables/   组合式函数
├── layouts/       页面布局
├── router/        路由（按模块拆文件）
├── stores/        Pinia 状态（按模块拆文件）
├── styles/        全局样式
├── types/         TypeScript 类型定义
├── utils/         工具函数
└── views/         页面（按模块分目录）
```

## 3. 模块化开发规则

### 3.1 封装要求

- 业务逻辑必须封装在 Action 或 Service 中，Controller 只负责参数校验和调用。
- 跨模块调用必须通过 Contract（接口）或 Event，不直接引用另一个模块的内部实现。
- 金额计算必须有独立的计算类，不允许在 Controller 或 Job 中直接写计算逻辑。
- 配置读取必须通过 Config 模块的 Service，不允许直接 DB 查询。

### 3.2 模块间通信

```text
推荐方式：
模块 A -> 发布 Event -> 模块 B 的 Listener 处理

允许方式：
模块 A -> 调用模块 B 的 Contract 接口

禁止方式：
模块 A -> 直接 new 模块 B 的内部 Service
模块 A -> 直接查询模块 B 的 Model
```

### 3.3 新增模块检查清单

新增一个模块时，必须完成以下步骤：

1. [ ] 创建 `docs/modules/<module>/README.md`
2. [ ] 在 `docs/modules/README.md` 中添加索引
3. [ ] 创建模块目录结构
4. [ ] 定义模块 Contract 接口
5. [ ] 注册模块 ServiceProvider
6. [ ] 写模块第一个测试
7. [ ] 更新 `docs/DEVELOPMENT_LOG.md`
8. [ ] 更新 `docs/PROJECT_STATE.md`

## 4. 代码完成标准

每次开发完成，以下条件必须全部满足：

### 4.1 代码层面

- 功能封装在对应模块中，不跨模块散落。
- 有单元测试覆盖核心逻辑（金额计算、状态变更、关系绑定）。
- 无 TODO 注释留在核心逻辑中（可以有 TODO 标记后续优化点，但核心逻辑必须完整）。
- 遵循项目命名规范。

### 4.2 文档层面

- 模块 README 已更新（已完成 / 待实现列表）。
- 开发日志已记录。
- 项目状态已更新。
- 如有架构变化，ARCHITECTURE.md 已更新。
- 如有新决策，DECISIONS.md 已更新。

### 4.3 禁止行为

- 禁止只写代码不更新文档。
- 禁止只更新文档不验证代码。
- 禁止跨模块硬耦合。
- 禁止在一次开发中做无关重构。
- 禁止引入文档中未记录的新依赖。
- 禁止修改其他模块的内部实现而不通知。

## 5. 开发顺序约束

每次开发一个功能时，必须按以下顺序执行：

```text
1. 读取 docs/PROJECT_STATE.md 确认当前状态
2. 读取目标模块文档确认边界和待实现列表
3. 明确本次目标和验收标准
4. 创建/更新目录结构（如有需要）
5. 编写核心逻辑
6. 编写测试
7. 验证通过
8. 更新模块文档
9. 更新开发日志
10. 更新项目状态
```

不允许跳过步骤 8-10。

## 6. 上下文恢复规则

当 AI 上下文被压缩或开启新会话时：

必须先读取：

```text
docs/AI_CONTEXT.md
docs/PROJECT_STATE.md
docs/AGENTS.md
docs/DEVELOPMENT_LOG.md
当前模块 README
```

然后才能继续开发。不读取就不开发。

## 7. 目录变动通知规则

任何目录结构变动（新增、重命名、移动、删除）必须：

1. 在当次开发中同步更新所有引用该目录的文档。
2. 在 DEVELOPMENT_LOG.md 中记录目录变动。
3. 在 PROJECT_STATE.md 中反映新的目录状态。
4. 检查 ARCHITECTURE.md 中的目录图是否需要更新。

违反此规则的开发视为未完成。
