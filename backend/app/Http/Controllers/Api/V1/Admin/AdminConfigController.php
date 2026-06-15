<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Config\Services\ConfigService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminConfigController extends Controller
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function show(): JsonResponse
    {
        return ApiResponse::ok([
            'items' => $this->configs->all(),
            'values' => $this->configs->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $items = $request->input('items', $request->input('values', $request->all()));
        if (! is_array($items)) {
            $items = [];
        }

        $flat = array_is_list($items) ? $items : Arr::dot($items);
        $error = $this->validateItems($flat);
        if ($error !== null) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, $error, null, 422);
        }

        $this->configs->updateBatch($flat, $request->user());

        return $this->show();
    }

    private function validateItems(array $items): ?string
    {
        foreach ($items as $key => $value) {
            if (is_array($value) && array_key_exists('key', $value)) {
                $key = (string) $value['key'];
                $value = $value['value'] ?? null;
            }

            $key = (string) $key;
            if (! array_key_exists($key, $this->rules())) {
                continue;
            }

            $rule = $this->rules()[$key];
            if (($rule['type'] ?? '') === 'number' && ! is_numeric($value)) {
                return $rule['name'].'必须是数字';
            }

            $num = is_numeric($value) ? (float) $value : null;
            if ($num !== null && array_key_exists('min', $rule) && $num < $rule['min']) {
                return $rule['name'].'不能小于 '.$rule['min'];
            }
            if ($num !== null && array_key_exists('max', $rule) && $num > $rule['max']) {
                return $rule['name'].'不能大于 '.$rule['max'];
            }
            if (($rule['type'] ?? '') === 'string' && ! in_array((string) $value, $rule['in'], true)) {
                return $rule['name'].'不正确';
            }
        }

        return null;
    }

    private function rules(): array
    {
        return [
            'milestone.amount' => ['name' => '里程碑金额', 'type' => 'number', 'min' => 0.01],
            'milestone.reward_amount' => ['name' => '里程碑奖励金额', 'type' => 'number', 'min' => 0],
            'milestone.max_times' => ['name' => '里程碑次数上限', 'type' => 'number', 'min' => 0, 'max' => 100],
            'rebate.pool_ratio' => ['name' => '返利池比例', 'type' => 'number', 'min' => 0, 'max' => 1],
            'rebate.decay_factor' => ['name' => '衰减系数', 'type' => 'number', 'min' => 0.000001, 'max' => 1],
            'payment.cny_to_credit_rate' => ['name' => '人民币额度换算比例', 'type' => 'number', 'min' => 0.000001],
            'withdraw.min_amount' => ['name' => '最低提现金额', 'type' => 'number', 'min' => 0.01],
            'withdraw.daily_limit' => ['name' => '每日提现次数', 'type' => 'number', 'min' => 1, 'max' => 100],
            'withdraw.freeze_days' => ['name' => '返利冻结天数', 'type' => 'number', 'min' => 0, 'max' => 365],
            'withdraw.review_mode' => ['name' => '提现审核模式', 'type' => 'string', 'in' => ['manual', 'auto']],
        ];
    }
}
