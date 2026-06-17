<?php

namespace App\Modules\Rebate\Services;

class DecayRebateCalculator
{
    public function calculate(float $pool, array $receiverIds, float $decay): array
    {
        $pool = $this->amount($pool);
        $decay = $decay > 0 ? $decay : 0.4;

        if ($pool <= 0 || $receiverIds === []) {
            return [];
        }

        $weights = [];
        foreach (array_values($receiverIds) as $index => $userId) {
            $weights[] = [
                'user_id' => (int) $userId,
                'level' => $index + 1,
                'weight' => pow($decay, $index),
            ];
        }

        $totalWeight = array_sum(array_column($weights, 'weight'));
        if ($totalWeight <= 0) {
            return [];
        }

        $rows = [];
        $sum = 0.0;

        foreach ($weights as $item) {
            $amount = $this->amount($pool * ((float) $item['weight'] / $totalWeight));
            $rows[] = [
                'user_id' => $item['user_id'],
                'level' => $item['level'],
                'weight' => $this->amount((float) $item['weight']),
                'amount' => $amount,
            ];
            $sum += $amount;
        }

        $diff = $this->amount($pool - $sum);
        if ($diff !== 0.0 && isset($rows[0])) {
            $rows[0]['amount'] = $this->amount($rows[0]['amount'] + $diff);
        }

        return $rows;
    }

    private function amount(float|int|string $value): float
    {
        return round((float) $value, 6);
    }
}
