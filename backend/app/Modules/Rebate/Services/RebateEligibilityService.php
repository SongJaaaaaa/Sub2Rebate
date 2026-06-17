<?php

namespace App\Modules\Rebate\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use Carbon\CarbonInterface;

class RebateEligibilityService
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_DISABLED = 'disabled';

    public function __construct(private readonly AuditLogService $audits)
    {
    }

    public function eligible(?User $user): bool
    {
        return $user instanceof User
            && (string) ($user->rebate_status ?: self::STATUS_ELIGIBLE) === self::STATUS_ELIGIBLE;
    }

    public function markRecharge(User $user, ?CarbonInterface $at = null, ?User $actor = null): void
    {
        $before = $user->toArray();
        $user->last_recharge_at = $at ?? now();

        if (! $this->eligible($user)) {
            $user->rebate_status = self::STATUS_ELIGIBLE;
            $user->rebate_disabled_at = null;
            $user->rebate_disabled_reason = null;
            $user->save();

            $this->record($actor, $user, 'rebate.eligibility_restored', $before, '充值恢复返利资格');

            return;
        }

        $user->save();
    }

    public function markBalanceDecrease(User $user, ?CarbonInterface $at = null): void
    {
        $user->last_balance_decreased_at = $at ?? now();
        $user->save();
    }

    public function disable(User $user, string $reason, ?User $actor = null, string $remark = '返利资格失效'): bool
    {
        if (! $this->eligible($user) && (string) $user->rebate_disabled_reason === $reason) {
            return false;
        }

        $before = $user->toArray();
        $user->rebate_status = self::STATUS_DISABLED;
        $user->rebate_disabled_at = now();
        $user->rebate_disabled_reason = $reason;
        $user->save();

        $this->record($actor, $user, 'rebate.eligibility_disabled', $before, $remark);

        return true;
    }

    private function record(?User $actor, User $user, string $action, array $before, string $remark): void
    {
        $this->audits->record('rebate', $action, [
            'actor' => $actor,
            'target' => $user,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'before_values' => $before,
            'after_values' => $user->toArray(),
            'remark' => $remark,
        ]);
    }
}
