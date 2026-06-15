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
4. 如果新增、修改、删除接口，更新 `docs/API_CONTRACT.md`
5. 如果有新增决策，更新 `docs/DECISIONS.md`
6. 如果修改架构，更新 `docs/ARCHITECTURE.md`

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
- 如有接口变更，`docs/API_CONTRACT.md` 已同步更新。
- 如有接口变更，已核对 `php artisan route:list --path=api/v1` 与文档一致。
- 模块进度已记录。
- 如影响用户行为，验收标准已说明。

如果文档没有更新，功能不算真正完成。

## 6. 接口文档规则

`docs/API_CONTRACT.md` 是前后端联调的唯一接口文档。

任何接口变更都必须同步记录：

- 新增接口：请求方法、路径、认证要求、参数、请求示例、响应示例、错误场景。
- 修改接口：字段名、字段类型、枚举、分页格式、错误码、示例响应。
- 删除接口：删除记录或标记废弃，并说明替代接口。

接口开发完成前必须检查：

```text
php artisan route:list --path=api/v1
```

如果接口实现和 `docs/API_CONTRACT.md` 不一致，优先修正文档或代码，不允许把不一致状态当作完成。

## 7. AI 协作规则

每次让 AI 继续开发时，优先让 AI 读取：

```text
docs/AI_CONTEXT.md
docs/AGENTS.md
docs/PROJECT_STATE.md
docs/DEVELOPMENT_LOG.md
当前模块 README
如涉及接口：docs/API_CONTRACT.md
```

AI 每次完成开发后必须：

- 写清楚做了什么。
- 写清楚没做什么。
- 更新文档。
- 如果新增、修改、删除接口，更新 `docs/API_CONTRACT.md` 并说明已核对路由。
- 检查目录变动是否需要同步其他文档。
- 提醒下一步建议。

AI 全局行为规范详见 `docs/AGENTS.md`。
