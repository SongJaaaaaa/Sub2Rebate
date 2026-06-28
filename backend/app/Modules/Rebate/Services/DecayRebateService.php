<?php

namespace App\Modules\Rebate\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Models\ConfigItem;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Rebate\Models\UserRebateProgress;
use Illuminate\Support\Facades\DB;

class DecayRebateService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly InviteService $invites,
        private readonly DecayRebateCalculator $calculator,
        private readonly RebateBalanceService $balances,
        private readonly AuditLogService $audits,
    ) {
    }

    public function process(RebateEvent $event): array
    {
        if ($event->event_type !== 'recharge') {
            return [
                'ok' => false,
                'processed' => false,
                'records' => [],
                'message' => '只处理充值事件',
            ];
        }

        if (RebateRecord::query()
            ->where('event_id', $event->id)
            ->where('type', RebateRecord::TYPE_DECAY)
            ->exists()) {
            return [
                'ok' => true,
                'processed' => false,
                'records' => [],
                'message' => '多级返利已处理',
            ];
        }

        $payer = User::query()->find((int) $event->user_id);
        if ($payer === null) {
            return [
                'ok' => false,
                'processed' => false,
                'records' => [],
                'message' => '充值用户不存在',
            ];
        }

        $stage = $this->stageResult($event);
        $triggerCount = (int) $stage['count'];
        $stageAmount = (float) $stage['stageAmount'];
        $poolAmount = (float) $stage['pool'];

        if ($triggerCount <= 0 || $poolAmount <= 0) {
            return [
                'ok' => true,
                'processed' => false,
                'records' => [],
                'triggerCount' => 0,
                'stageAmount' => $this->money($stageAmount),
                'poolAmount' => $this->money(0),
                'message' => '未达到多级返利台阶',
            ];
        }

        $ancestors = $this->invites->rebateAncestors($payer);
        if ($ancestors === []) {
            $event->error_message = '没有上级，未发放多级返利';
            $event->save();

            return [
                'ok' => true,
                'processed' => true,
                'records' => [],
                'triggerCount' => $triggerCount,
                'stageAmount' => $this->money($stageAmount),
                'poolAmount' => $this->money($poolAmount),
                'message' => '没有上级',
            ];
        }

        $maxDepth = max(1, (int) $this->configs->get('rebate.max_depth', 5));
        $ancestors = array_slice($ancestors, 0, $maxDepth);

        $mode = $this->inactiveNodeMode();
        $calcAncestors = $mode === 'exclude_recalculate'
            ? array_values(array_filter($ancestors, fn (array $row): bool => $this->isEligible($row)))
            : $ancestors;
        $receiverIds = array_map(fn (array $row): int => (int) $row['user_id'], $calcAncestors);

        $custom = $this->customItems($payer, $receiverIds, $poolAmount);
        if ($custom !== null) {
            $pool = $custom['pool'];
            $items = $custom['items'];
            $snapshot = $custom['snapshot'];
        } else {
            $decay = $this->configFloat('rebate.decay_factor', 0.4);
            $pool = $poolAmount;
            $items = $this->calculator->calculate($pool, $receiverIds, $decay);
            $snapshot = [
                'rebate.stage_amount' => $this->money($stageAmount),
                'rebate.stage_reward_amount' => $this->money($triggerCount > 0 ? $poolAmount / $triggerCount : $poolAmount),
                'rebate.trigger_count' => $triggerCount,
                'rebate.decay_factor' => $this->money($decay),
                'rebate.max_depth' => $maxDepth,
                'rebate.pool_amount' => $this->money($pool),
            ];
        }

        if ($mode === 'platform') {
            $eligibleIds = array_flip(array_map(
                fn (array $row): int => (int) $row['user_id'],
                array_values(array_filter($ancestors, fn (array $row): bool => $this->isEligible($row)))
            ));
            $items = array_values(array_filter($items, fn (array $item): bool => isset($eligibleIds[(int) $item['user_id']])));
        }

        $snapshot += [
            'rebate.inactive_node_mode' => $mode,
            'rebate.skipped_disabled_user_ids' => array_values(array_map(
                fn (array $row): int => (int) $row['user_id'],
                array_filter($ancestors, fn (array $row): bool => ! $this->isEligible($row))
            )),
        ];

        return DB::transaction(function () use ($event, $payer, $items, $pool, $snapshot, $triggerCount, $stageAmount, $poolAmount): array {
            $records = [];

            foreach ($items as $item) {
                $record = RebateRecord::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'receiver_user_id' => $item['user_id'],
                        'level' => $item['level'],
                        'type' => RebateRecord::TYPE_DECAY,
                    ],
                    [
                        'payer_user_id' => $payer->id,
                        'source_amount' => $this->money($poolAmount),
                        'rebate_amount' => $this->money($item['amount']),
                        'status' => 'confirmed',
                        'config_snapshot' => $snapshot + [
                            'rebate.weight' => $this->money($item['weight']),
                        ],
                        'remark' => '多级返利分配',
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $this->balances->addAvailable((int) $item['user_id'], (float) $item['amount']);
                    $this->audits->record('rebate', 'rebate.decay_granted', [
                        'actor_user_id' => null,
                        'target_user_id' => (int) $item['user_id'],
                        'subject_type' => RebateRecord::class,
                        'subject_id' => $record->id,
                        'after_values' => [
                            'event_id' => $event->id,
                            'payer_user_id' => $payer->id,
                            'receiver_user_id' => (int) $item['user_id'],
                            'level' => (int) $item['level'],
                            'amount' => $this->money($item['amount']),
                        ],
                        'remark' => '多级返利分配发放',
                    ]);
                }

                $records[] = $record;
            }

            return [
                'ok' => true,
                'processed' => true,
                'records' => $records,
                'triggerCount' => $triggerCount,
                'stageAmount' => $this->money($stageAmount),
                'poolAmount' => $this->money($pool),
            ];
        });
    }

    private function stageResult(RebateEvent $event): array
    {
        $progress = UserRebateProgress::query()
            ->where('user_id', $event->user_id)
            ->first();

        if ($progress === null) {
            return [
                'count' => 0,
                'stageAmount' => $this->configFloat('rebate.stage_amount', 100),
                'pool' => 0.0,
            ];
        }

        $milestoneAmount = $this->configFloat('milestone.amount', 100);
        $maxTimes = max(0, (int) $this->configs->get('milestone.max_times', 2));
        $milestoneEndAmount = $milestoneAmount * $maxTimes;
        $stageAmount = $this->configFloat('rebate.stage_amount', 100);
        $stageReward = $this->configFloat('rebate.stage_reward_amount', 15);
        $after = (float) $progress->total_recharge_amount;
        $before = max(0.0, $after - (float) $event->standard_amount);
        $afterNormal = max(0.0, $after - $milestoneEndAmount);
        $beforeNormal = max(0.0, $before - $milestoneEndAmount);
        $afterCount = $stageAmount > 0 ? (int) floor($afterNormal / $stageAmount) : 0;
        $beforeCount = $stageAmount > 0 ? (int) floor($beforeNormal / $stageAmount) : 0;
        $count = max(0, $afterCount - $beforeCount);

        return [
            'count' => $count,
            'stageAmount' => $stageAmount,
            'pool' => $this->amount($count * $stageReward),
        ];
    }

    private function customItems(User $payer, array $receiverIds, float $poolAmount): ?array
    {
        $item = ConfigItem::query()->where('key', 'rebate.user_override.'.$payer->id)->first();
        if (! $item instanceof ConfigItem || ! is_array($item->value)) {
            return null;
        }

        $value = $item->value;
        if (! (bool) ($value['enabled'] ?? false)) {
            return null;
        }

        $rates = [];
        foreach (($value['customRates'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $level = (int) ($row['level'] ?? 0);
            $rate = $this->amount($row['rate'] ?? 0);
            if ($level > 0 && $rate > 0) {
                $rates[$level] = $rate;
            }
        }

        if ($rates === []) {
            return null;
        }

        $rows = [];
        $pool = 0.0;
        $expected = 0.0;
        foreach (array_values($receiverIds) as $index => $userId) {
            $level = $index + 1;
            $rate = (float) ($rates[$level] ?? 0);
            if ($rate <= 0) {
                continue;
            }

            $expected += $poolAmount * $rate;
            $amount = $this->amount($poolAmount * $rate);
            if ($amount <= 0) {
                continue;
            }

            $pool += $amount;
            $rows[] = [
                'user_id' => (int) $userId,
                'level' => $level,
                'weight' => $rate,
                'amount' => $amount,
            ];
        }

        if ($rows === []) {
            return null;
        }

        $target = $this->amount($expected);
        $diff = $this->amount($target - $pool);
        if ($diff !== 0.0) {
            $rows[0]['amount'] = $this->amount($rows[0]['amount'] + $diff);
            $pool = $this->amount($pool + $diff);
        }

        return [
            'items' => $rows,
            'pool' => $pool,
            'snapshot' => [
                'rebate.override_user_id' => (int) $payer->id,
                'rebate.override_config_id' => (int) $item->id,
                'rebate.pool_amount' => $this->money($pool),
                'rebate.override_mode' => 'payer_custom_rates',
            ],
        ];
    }

    private function configFloat(string $key, float $default): float
    {
        $value = $this->configs->get($key, (string) $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    private function inactiveNodeMode(): string
    {
        $mode = (string) $this->configs->get('rebate.inactive_node_mode', 'platform');

        return in_array($mode, ['platform', 'exclude_recalculate'], true) ? $mode : 'platform';
    }

    private function isEligible(array $row): bool
    {
        return (string) ($row['rebate_status'] ?? RebateEligibilityService::STATUS_ELIGIBLE) === RebateEligibilityService::STATUS_ELIGIBLE;
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 6) : 0.0;
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
