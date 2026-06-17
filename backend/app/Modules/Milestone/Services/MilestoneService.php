<?php

namespace App\Modules\Milestone\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Rebate\Models\UserRebateProgress;
use App\Modules\Rebate\Services\RebateEligibilityService;
use App\Modules\Rebate\Services\RebateBalanceService;
use Illuminate\Support\Facades\DB;

class MilestoneService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly InviteService $invites,
        private readonly RebateBalanceService $balances,
        private readonly RebateEligibilityService $eligibility,
        private readonly AuditLogService $audits,
    ) {
    }

    public function process(RebateEvent $event): array
    {
        if ($event->status === RebateEvent::STATUS_PROCESSED) {
            return [
                'ok' => true,
                'processed' => false,
                'records' => [],
                'message' => '事件已处理',
            ];
        }

        if ($event->event_type !== 'recharge') {
            return [
                'ok' => false,
                'processed' => false,
                'records' => [],
                'message' => '只处理充值事件',
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

        return DB::transaction(function () use ($event, $payer): array {
            $progress = UserRebateProgress::query()->firstOrCreate(
                ['user_id' => $payer->id],
                [
                    'total_recharge_amount' => '0',
                    'milestone_times' => 0,
                    'last_event_id' => null,
                ]
            );

            if ((int) $progress->last_event_id === (int) $event->id) {
                return [
                    'ok' => true,
                    'processed' => false,
                    'records' => [],
                    'message' => '事件已计入进度',
                ];
            }

            $beforeAmount = (float) $progress->total_recharge_amount;
            $afterAmount = $beforeAmount + (float) $event->standard_amount;
            $amount = $this->configFloat('milestone.amount', 100);
            $rewardAmount = $this->configFloat('milestone.reward_amount', 15);
            $maxTimes = max(0, (int) $this->configs->get('milestone.max_times', 2));

            $beforeReached = $amount > 0 ? (int) floor($beforeAmount / $amount) : 0;
            $afterReached = $amount > 0 ? (int) floor($afterAmount / $amount) : 0;
            $canTrigger = max(0, min($afterReached, $maxTimes) - max((int) $progress->milestone_times, $beforeReached));

            $records = [];
            $parent = $this->directParent($payer);
            $parentId = $parent instanceof User ? (int) $parent->id : null;

            $awardedTimes = 0;
            $consumedTimes = 0;

            if ($canTrigger > 0 && $parentId !== null) {
                $consumedTimes = $canTrigger;

                if ($this->eligibility->eligible($parent)) {
                    $totalReward = $rewardAmount * $canTrigger;
                    $record = RebateRecord::query()->firstOrCreate(
                        [
                            'event_id' => $event->id,
                            'receiver_user_id' => $parentId,
                            'level' => 1,
                            'type' => RebateRecord::TYPE_MILESTONE,
                        ],
                        [
                            'payer_user_id' => $payer->id,
                            'source_amount' => $event->standard_amount,
                            'rebate_amount' => $this->money($totalReward),
                            'status' => 'confirmed',
                            'config_snapshot' => [
                                'milestone.amount' => $this->money($amount),
                                'milestone.reward_amount' => $this->money($rewardAmount),
                                'milestone.max_times' => $maxTimes,
                                'milestone.triggered_times' => $canTrigger,
                                'receiver.rebate_status' => (string) ($parent->rebate_status ?: RebateEligibilityService::STATUS_ELIGIBLE),
                            ],
                            'remark' => '里程碑奖励',
                        ]
                    );

                    if ($record->wasRecentlyCreated) {
                        $this->balances->addAvailable($parentId, $totalReward);
                        $this->audits->record('rebate', 'rebate.milestone_granted', [
                            'actor_user_id' => null,
                            'target_user_id' => $parentId,
                            'subject_type' => RebateRecord::class,
                            'subject_id' => $record->id,
                            'after_values' => [
                                'event_id' => $event->id,
                                'payer_user_id' => $payer->id,
                                'receiver_user_id' => $parentId,
                                'amount' => $this->money($totalReward),
                                'triggered_times' => $canTrigger,
                            ],
                            'remark' => '里程碑奖励发放',
                        ]);
                    }

                    $records[] = $record;
                    $awardedTimes = $canTrigger;
                }
            }

            $progress->total_recharge_amount = $this->money($afterAmount);
            $progress->milestone_times = min($maxTimes, (int) $progress->milestone_times + $consumedTimes);
            $progress->last_event_id = $event->id;
            $progress->save();

            return [
                'ok' => true,
                'processed' => true,
                'records' => $records,
                'triggeredTimes' => $awardedTimes,
            ];
        });
    }

    private function directParent(User $payer): ?User
    {
        $ids = $this->invites->ancestorIds($payer);
        $id = $ids[0] ?? null;

        return $id !== null ? User::query()->find($id) : null;
    }

    private function configFloat(string $key, float $default): float
    {
        $value = $this->configs->get($key, (string) $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
