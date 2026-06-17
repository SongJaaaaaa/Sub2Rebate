<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Carbon\CarbonInterface;

trait FormatsApiPayloads
{
    private function pageParams(int $page, int $pageSize): array
    {
        return [
            max(1, $page),
            max(1, min($pageSize, 100)),
        ];
    }

    private function money(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }

    private function time(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $value instanceof CarbonInterface
            ? $value->timezone('Asia/Shanghai')->format('Y-m-d H:i:s')
            : (string) $value;
    }
}
