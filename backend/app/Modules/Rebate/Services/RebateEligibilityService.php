<?php

namespace App\Modules\Rebate\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Services\ConfigService;
use Carbon\CarbonInterface;

class RebateEligibilityService
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_DISABLED = 'disabled';

    public function __construct(
        private readonly AuditLogService $audits,
        private readonly ConfigService $configs,
    ) {
    }

    public function eligible(?User $user): bool
    {
        return $user instanceof User
            && (string) ($user->rebate_status ?: self::STATUS_ELIGIBLE) === self::STATUS_ELIGIBLE;
    }

    public function recordRechargeActivity(User $user, ?CarbonInterface $at = null): void
    {
        $user->last_recharge_at = $at ?? now();
        $user->save();
    }

    public function recordSuccessfulRecharge(User $user, float|int|string $amount, ?CarbonInterface $at = null, ?User $actor = null): bool
    {
        $before = $user->toArray();
        $user->last_recharge_at = $at ?? now();

        if (! $this->eligible($user) && $this->canRestore($amount)) {
            $user->rebate_status = self::STATUS_ELIGIBLE;
            $user->rebate_disabled_at = null;
            $user->rebate_disabled_reason = null;
            $user->save();

            $this->record($actor, $user, 'rebate.eligibility_restored', $before, '充值恢复返利资格');

            return true;
        }

        $user->save();

        return false;
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

    private function canRestore(float|int|string $amount): bool
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $min = $this->restoreMinRecharge();

        return $value >= $min;
    }

    private function restoreMinRecharge(): float
    {
        $value = $this->configs->get('risk.lie_flat_restore_min_recharge', '10');

        return is_numeric($value) ? max(0.0, (float) $value) : 10.0;
    }
}
