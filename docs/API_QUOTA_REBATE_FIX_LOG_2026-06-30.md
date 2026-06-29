# API 额度调整返利开关修复日志

日期：2026-06-30

## 背景

管理员在「Sub2API 额度管理」中直接给用户增加 API 额度时，旧逻辑默认把这次调整当作充值事件，写入 `payment_records` 和 `rebate_events`，进而可能触发上级返利。

这会混淆两种业务动作：

- 管理员补额度 / 测试额度 / 活动赠送：只应增加 Sub2API 余额，不应参与返利。
- 管理员代用户充值：需要明确选择参与返利。

## 修复内容

1. 管理端 API 额度调整新增「参与返利」开关，默认关闭。
2. 默认关闭时：
   - 只调用 Sub2API 增加余额。
   - 写入本系统审计日志。
   - 不写 `payment_records`。
   - 不写 `rebate_events`。
   - 不触发返利。
3. 开关打开时：
   - 保持代充值口径。
   - 创建充值流水和返利事件。
   - 立即派发返利事件处理。
4. Sub2API 额度变动记录统一展示：
   - 本系统审计记录。
   - Sub2API 余额历史记录。
   - 来源、备注、是否参与返利。
5. 用户端「额度充值 -> 最近充值订单」统一展示：
   - 用户充值订单。
   - 管理员 API 额度调整记录。
   - 来源、备注、是否参与返利。
6. API 额度快照在 Sub2API `total_recharged = 0` 时，使用本系统已支付充值流水兜底展示累计充入。
7. 返利进度处理时使用本系统已支付总额兜底，避免历史充值流水已存在但进度表落后的情况下计算错误。

## 关键文件

- `backend/app/Http/Controllers/Api/V1/Admin/AdminBalanceController.php`
- `backend/app/Modules/Payment/Services/RechargeOrderService.php`
- `backend/app/Modules/Milestone/Services/MilestoneService.php`
- `frontend/src/views/admin/AdminApiQuotaView.vue`
- `frontend/src/views/recharge/RechargeView.vue`

## 验证

已通过：

- `php artisan test --filter AdminApiTest`
- `php artisan test --filter RechargeOrderFlowTest`
- `php artisan test --filter DeepApiEndpointTest`
- `php artisan test --filter RebateEventJobTest`
- `npm run build`

构建说明：本机默认 Node 版本过旧，已使用 Codex bundled Node 执行前端构建。
