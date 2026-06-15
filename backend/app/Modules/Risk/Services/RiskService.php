<?php

namespace App\Modules\Risk\Services;

use App\Models\User;
use App\Modules\Risk\Models\RiskFlag;

class RiskService
{
    public function canWithdraw(User $user): array
    {
        $flag = RiskFlag::query()
            ->where('user_id', $user->id)
            ->where('status', RiskFlag::STATUS_ACTIVE)
            ->whereIn('type', [RiskFlag::TYPE_BLACKLIST, RiskFlag::TYPE_WITHDRAW_FREEZE])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        if (! $flag instanceof RiskFlag) {
            return [
                'ok' => true,
            ];
        }

        return [
            'ok' => false,
            'message' => $flag->type === RiskFlag::TYPE_BLACKLIST ? '当前账号不可提现' : '当前账号提现已冻结',
            'flag' => $flag,
        ];
    }

    public function flagUser(User $user, string $type, string $reason = '', ?User $operator = null): RiskFlag
    {
        return RiskFlag::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => RiskFlag::STATUS_ACTIVE,
            'reason' => $reason,
            'created_by' => $operator?->id,
            'expires_at' => null,
        ]);
    }
}
