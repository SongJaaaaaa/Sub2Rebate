# Sub2Rebate 项目文档中心

> 本目录是项目的长期记忆中心。后续每次开发、调整需求、修复问题、完成模块，都必须同步更新相关文档，避免因为上下文压缩、人员切换或时间间隔导致目标丢失。

## 1. 文档目标

本项目采用“文档驱动开发”方式：

- 先明确目标，再写代码。
- 每个模块都有独立文档。
- 每次开发都有记录。
- 每次关键技术选择都有决策记录。
- 每次上下文交接都能从文档恢复当前状态。

## 2. 推荐技术栈

当前已确定的项目技术栈：

```text
后端：Laravel 13
管理后台：Filament 5
用户前端：Vue 3 + Vite
UI 框架：Element Plus
状态管理：Pinia
路由：Vue Router
请求库：Axios
数据库：PostgreSQL
缓存/队列：Redis
部署：Docker + Nginx
```

## 3. 文档目录

```text
docs/
├── README.md                  文档中心入口
├── PROJECT_GUIDELINES.md      项目规范
├── ARCHITECTURE.md            总体架构
├── ROADMAP.md                 版本路线图
├── DEVELOPMENT_PROCESS.md     开发流程与文档更新规则
├── DEVELOPMENT_LOG.md         全局开发日志
├── PROJECT_STATE.md           当前项目状态
├── DECISIONS.md               技术/产品决策记录
├── AI_CONTEXT.md              AI 交接上下文
├── AGENTS.md                  AI Agents 全局规范
├── MODULE_TEMPLATE.md         模块文档模板
└── modules/                   各模块文档
```

## 4. 每次开发必须更新的文档

每次开始或完成开发时，至少检查并更新：

1. `docs/PROJECT_STATE.md`
2. `docs/DEVELOPMENT_LOG.md`
3. 当前开发模块的 `docs/modules/<module>/README.md`
4. 如果出现重要方案选择，更新 `docs/DECISIONS.md`
5. 如果影响整体架构，更新 `docs/ARCHITECTURE.md`

## 5. 模块列表

| 模块 | 文档 | 说明 |
|---|---|---|
| Invite | `docs/modules/invite/README.md` | 邀请关系、推广链接、邀请树 |
| Milestone | `docs/modules/milestone/README.md` | 新人累计充值里程碑奖励 |
| Rebate | `docs/modules/rebate/README.md` | 多级返利、归一化、贡献系数 |
| Withdraw | `docs/modules/withdraw/README.md` | 提现申请、审核、打款 |
| Payment | `docs/modules/payment/README.md` | 支付宝充值与支付回调 |
| Config | `docs/modules/config/README.md` | 后台配置中心与 Tips |
| Risk | `docs/modules/risk/README.md` | 风控、防刷、黑名单 |
| Audit | `docs/modules/audit/README.md` | 流水、日志、审计、对账 |
| User Center | `docs/modules/user-center/README.md` | Vue 用户中心 |
| Admin | `docs/modules/admin/README.md` | Filament 管理后台 |
| Sub2API Integration | `docs/modules/integration-sub2api/README.md` | 与 Sub2API 的集成 |

## 6. 当前最重要原则

第一版先完成闭环：

```text
邀请树
-> 新人里程碑奖励
-> 后续多级返利
-> 独立返利余额
-> 人工提现
-> 完整流水
```

支付宝自动化、贡献系数、防躺平、活动配置放到后续阶段逐步开启。

