<?php

namespace App\Modules\Rebate\Services;

use App\Modules\Rebate\Models\RebateBalance;
use Illuminate\Support\Facades\DB;

class RebateBalanceService
{
    public function addAvailable(int $userId, float $amount): RebateBalance
    {
        $balance = RebateBalance::query()->createOrFirst(
            ['user_id' => $userId],
            [
                'available_amount' => '0',
                'frozen_amount' => '0',
                'withdrawn_amount' => '0',
            ]
        );

        RebateBalance::query()
            ->whereKey($balance->id)
            ->update([
                'available_amount' => DB::raw('available_amount + '.$this->money($amount)),
                'updated_at' => now(),
            ]);

        return $balance->refresh();
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
