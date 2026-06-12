# 开发流程与文档更新规则

## 1. 每次开发前

开始任何开发前，必须先阅读：

1. `docs/PROJECT_STATE.md`
2. `docs/ROADMAP.md`
3. 当前模块文档：`docs/modules/<module>/README.md`
4. 如涉及架构，阅读 `docs/ARCHITECTURE.md`

然后明确：

- 本次目标。
- 涉及模块。
- 不做什么。
- 验收标准。

## 2. 每次开发中

开发过程中必须遵守：

- 不做无关重构。
- 不跨模块随意耦合。
- 金额计算必须有流水。
- 状态变更必须可追溯。
- 重要决策立即写入 `docs/DECISIONS.md`。

## 3. 每次开发后

完成开发后必须更新：

1. `docs/DEVELOPMENT_LOG.md`
2. `docs/PROJECT_STATE.md`
3. 涉及模块的 `docs/modules/<module>/README.md`
4. 如果有新增决策，更新 `docs/DECISIONS.md`
5. 如果修改架构，更新 `docs/ARCHITECTURE.md`

## 4. 模块文档必须记录

每个模块文档至少包含：

- 模块目标。
- 模块边界。
- 已完成内容。
- 待实现内容。
- 数据结构。
- API 或页面。
- 关键规则。
- 本模块开发日志。

## 5. Definition of Done

一个功能完成必须满足：

- 代码实现完成。
- 基础测试或手动验证完成。
- 文档已更新。
- 模块进度已记录。
- 如影响用户行为，验收标准已说明。

如果文档没有更新，功能不算真正完成。

## 6. AI 协作规则

每次让 AI 继续开发时，优先让 AI 读取：

```text
docs/AI_CONTEXT.md
docs/AGENTS.md
docs/PROJECT_STATE.md
docs/DEVELOPMENT_LOG.md
当前模块 README
```

AI 每次完成开发后必须：

- 写清楚做了什么。
- 写清楚没做什么。
- 更新文档。
- 检查目录变动是否需要同步其他文档。
- 提醒下一步建议。

AI 全局行为规范详见 `docs/AGENTS.md`。

