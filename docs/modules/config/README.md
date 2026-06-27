# Config 模块：配置中心

## 1. 模块目标

负责管理返利、里程碑、提现、防躺平、活动等配置。

## 2. 模块边界

本模块负责：

- 配置读取。
- 配置修改。
- 配置 Tips。
- 配置审计。

本模块不负责：

- 具体返利计算实现。
- 提现打款。

## 3. 配置分组

```text
milestone     里程碑配置
rebate        多级返利配置
payment       充值金额和额度换算配置
withdraw      提现配置
risk          风控配置
activity      活动配置（第一版不实现）
contribution  贡献系数配置（第一版只预留，不启用）
```

## 4. 第一版配置项

### 4.1 里程碑配置

| 配置键 | 默认值 | Tips |
|---|---:|---|
| milestone.amount | 100 | 新人累计充值每达到该金额，触发一次直接上级奖励 |
| milestone.reward_amount | 15 | 每次里程碑触发时，直接上级获得的返利金额 |
| milestone.max_times | 2 | 同一个新人最多触发多少次里程碑奖励 |
| milestone.only_direct | true | 开启后，里程碑阶段只奖励直接上级 |

### 4.2 多级返利配置

| 配置键 | 默认值 | Tips |
|---|---:|---|
| rebate.pool_ratio | 0.15 | 返利池比例，充值 100 元返利池为 15 元 |
| rebate.mode | decay | 分发模式：decay 衰减系数模式 |
| rebate.decay_factor | 0.4 | 衰减系数，每增加一级权重乘以该值，越小则上级集中度越高 |
| rebate.normalize | true | 归一化：无失效节点时将返利池全部分配给有效上级 |
| rebate.inactive_node_mode | platform | 失效节点返利处理方式：platform 归平台；exclude_recalculate 排除失效节点后重算 |

说明：不设最大层级限制，按实际邀请链路的所有上级计算，无限级金字塔结构。深层级通过衰减系数自然递减到极小值。邀请链路中存在返利失效节点时，按 `rebate.inactive_node_mode` 决定该节点对应金额归平台，还是排除后对剩余上级重新归一化。

### 4.3 充值金额配置

| 配置键 | 默认值 | Tips |
|---|---:|---|
| payment.cny_to_credit_rate | 1 | 人民币与 Sub2API 额度/刀的换算比例，当前默认 1 元 = 1 额度 |

说明：返利计算使用 Sub2Rebate 的标准金额口径。Sub2API 来源金额进入 `rebate_events` 时，需要保存原始金额、标准金额和当时使用的换算配置快照。

### 4.4 提现配置

| 配置键 | 默认值 | Tips |
|---|---:|---|
| withdraw.min_amount | 50 | 最低提现金额 |
| withdraw.review_mode | manual | 审核模式：manual 人工审核 |
| withdraw.daily_limit | 1 | 每日提现次数上限 |
| withdraw.freeze_days | 0 | 新获得返利冻结天数，0 为不冻结 |

### 4.5 风控配置

| 配置键 | 默认值 | Tips |
|---|---:|---|
| risk.blacklist_enabled | true | 是否启用黑名单 |
| risk.duplicate_check | true | 防重复发放检查 |
| risk.lie_flat_enabled | true | 是否启用轻量防躺平检测 |
| risk.lie_flat_days | 7 | 连续多少天无充值、余额无减少、无新增下级后置灰并暂停返利资格 |

## 5. 核心要求

- 每个配置项必须有 Tips。
- 配置修改必须记录审计日志。
- 金额和比例配置必须校验合法范围。
- 关键配置修改应记录修改前后值。
- 涉及充值、返利、提现的配置修改只能由管理员执行。
- 当前 1 人民币 = 1 Sub2API 额度/刀只是默认配置，不能写死在业务代码里。

## 6. 已完成

- 模块文档初始化。
- 明确第一版配置项。
- 已实现 `config_items` 表。
- 已实现默认配置清单和 `ConfigService` 读取服务。
- 已实现 `GET /api/v1/config/items`，返回配置项、Tips 和嵌套 `values`。
- 已实现默认配置自动补齐和 `ConfigItemSeeder`。
- 已实现后台配置批量更新和审计日志。
- 已实现关键配置基础范围校验：金额必须非负或大于 0，比例和衰减系数限定在合理区间。
- 已补充 `ConfigItemsTest`，覆盖默认配置读取、服务读取和未登录拦截。

## 7. 待实现

- 金额换算配置快照写入充值事件。
- `rebate.inactive_node_mode` 默认配置、校验、接口返回和配置中心 Tooltip。
- `risk.lie_flat_enabled`、`risk.lie_flat_days` 默认配置、校验、接口返回和配置中心 Tooltip。

## 8. 开发记录

### 2026-06-13

- 目标：建立模块文档。
- 完成：明确配置中心分组和第一版配置项。
- 变更：多级分发从固定比例改为衰减系数模式；里程碑次数从 3 改为 2。
- 变更：补充 `payment.cny_to_credit_rate`，当前默认 1 元 = 1 Sub2API 额度/刀；提现最低金额默认 50。
- 变更：每日提现次数默认 1 次，返利冻结天数默认 0。
- 下一步：实现配置表、读取服务和 Filament 配置页面。

### 2026-06-13

- 目标：落地 B3 配置中心后端读取基础。
- 完成：新增 `config_items` 迁移、默认配置、`ConfigService`、`GET /api/v1/config/items` 和测试。
- 说明：当前只做配置读取；配置修改、管理员备注和审计归入 B10 Filament 后台阶段。

### 2026-06-14

- 目标：补齐后台配置更新的基础安全边界。
- 完成：Admin API 更新配置时校验关键金额、比例、次数和审核模式范围。
- 完成：配置更新写审计日志，并默认记录 IP / User-Agent。
- 验证：后端全量测试通过，141 个测试、668 个断言。
