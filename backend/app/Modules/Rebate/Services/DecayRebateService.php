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
                'message' => '衰减返利已处理',
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

        $normalAmount = $this->normalAmount($event);
        if ($normalAmount <= 0) {
            return [
                'ok' => true,
                'processed' => false,
                'records' => [],
                'normalAmount' => $this->money(0),
                'message' => '里程碑阶段未结束',
            ];
        }

        $ancestors = $this->invites->rebateAncestors($payer);
        if ($ancestors === []) {
            $event->error_message = '没有上级，未发放衰减返利';
            $event->save();

            return [
                'ok' => true,
                'processed' => true,
                'records' => [],
                'normalAmount' => $this->money($normalAmount),
                'message' => '没有上级',
            ];
        }

        $mode = $this->inactiveNodeMode();
        $calcAncestors = $mode === 'exclude_recalculate'
            ? array_values(array_filter($ancestors, fn (array $row): bool => $this->isEligible($row)))
            : $ancestors;
        $receiverIds = array_map(fn (array $row): int => (int) $row['user_id'], $calcAncestors);

        $custom = $this->customItems($payer, $receiverIds, $normalAmount);
        if ($custom !== null) {
            $pool = $custom['pool'];
            $items = $custom['items'];
            $snapshot = $custom['snapshot'];
        } else {
            $poolRatio = $this->configFloat('rebate.pool_ratio', 0.15);
            $decay = $this->configFloat('rebate.decay_factor', 0.4);
            $pool = $normalAmount * $poolRatio;
            $items = $this->calculator->calculate($pool, $receiverIds, $decay);
            $snapshot = [
                'rebate.pool_ratio' => $this->money($poolRatio),
                'rebate.decay_factor' => $this->money($decay),
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

        return DB::transaction(function () use ($event, $payer, $items, $pool, $snapshot, $normalAmount): array {
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
                        'source_amount' => $this->money($normalAmount),
                        'rebate_amount' => $this->money($item['amount']),
                        'status' => 'confirmed',
                        'config_snapshot' => $snapshot + [
                            'rebate.weight' => $this->money($item['weight']),
                        ],
                        'remark' => '多级衰减返利',
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
                        'remark' => '多级衰减返利发放',
                    ]);
                }

                $records[] = $record;
            }

            return [
                'ok' => true,
                'processed' => true,
                'records' => $records,
                'normalAmount' => $this->money($normalAmount),
                'poolAmount' => $this->money($pool),
            ];
        });
    }

    private function normalAmount(RebateEvent $event): float
    {
        $progress = UserRebateProgress::query()
            ->where('user_id', $event->user_id)
            ->first();

        if ($progress === null) {
            return 0.0;
        }

        $milestoneAmount = $this->configFloat('milestone.amount', 100);
        $maxTimes = max(0, (int) $this->configs->get('milestone.max_times', 2));
        $threshold = $milestoneAmount * $maxTimes;
        $after = (float) $progress->total_recharge_amount;
        $before = max(0.0, $after - (float) $event->standard_amount);

        return max(0.0, $after - $threshold) - max(0.0, $before - $threshold);
    }

    private function customItems(User $payer, array $receiverIds, float $normalAmount): ?array
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

            $expected += $normalAmount * $rate;
            $amount = $this->amount($normalAmount * $rate);
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
