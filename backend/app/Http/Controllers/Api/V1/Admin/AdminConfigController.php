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

    public function payment(): JsonResponse
    {
        return ApiResponse::ok($this->paymentPayload());
    }

    public function updatePayment(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled', true);
        $qrUrl = trim((string) $request->input('qrUrl', ''));
        $displayName = trim((string) $request->input('displayName', ''));
        $note = trim((string) $request->input('note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。'));
        $expireMinutes = (int) $request->input('expireMinutes', 15);
        $creditRate = trim((string) $request->input('creditRate', '1'));

        if ($enabled && $qrUrl === '') {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '开启二维码充值时必须提供支付宝二维码', null, 422);
        }

        if ($expireMinutes < 1 || $expireMinutes > 1440) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '充值订单有效期必须在 1 到 1440 分钟之间', null, 422);
        }

        if (! is_numeric($creditRate) || (float) $creditRate <= 0) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '人民币额度换算比例必须大于 0', null, 422);
        }

        $this->configs->updateBatch([
            'payment.qr_enabled' => $enabled,
            'payment.alipay_qr_url' => $qrUrl,
            'payment.alipay_display_name' => $displayName,
            'payment.qr_note' => $note,
            'payment.order_expire_minutes' => $expireMinutes,
            'payment.cny_to_credit_rate' => $creditRate,
        ], $request->user());

        return $this->payment();
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
            if (($rule['type'] ?? '') === 'string' && array_key_exists('in', $rule) && ! in_array((string) $value, $rule['in'], true)) {
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
            'rebate.inactive_node_mode' => ['name' => '失效节点返利处理方式', 'type' => 'string', 'in' => ['platform', 'exclude_recalculate']],
            'payment.cny_to_credit_rate' => ['name' => '人民币额度换算比例', 'type' => 'number', 'min' => 0.000001],
            'payment.order_expire_minutes' => ['name' => '充值订单有效期', 'type' => 'number', 'min' => 1, 'max' => 1440],
            'withdraw.min_amount' => ['name' => '最低提现金额', 'type' => 'number', 'min' => 0.01],
            'withdraw.daily_limit' => ['name' => '每日提现次数', 'type' => 'number', 'min' => 1, 'max' => 100],
            'withdraw.freeze_days' => ['name' => '返利冻结天数', 'type' => 'number', 'min' => 0, 'max' => 365],
            'withdraw.review_mode' => ['name' => '提现审核模式', 'type' => 'string', 'in' => ['manual', 'auto']],
            'risk.lie_flat_days' => ['name' => '连续无活跃天数', 'type' => 'number', 'min' => 1, 'max' => 365],
            'risk.lie_flat_restore_min_recharge' => ['name' => '置灰恢复最低充值金额', 'type' => 'number', 'min' => 0],
        ];
    }

    private function paymentPayload(): array
    {
        return [
            'enabled' => (bool) $this->configs->get('payment.qr_enabled', true),
            'channel' => 'alipay',
            'qrUrl' => trim((string) $this->configs->get('payment.alipay_qr_url', '')),
            'displayName' => trim((string) $this->configs->get('payment.alipay_display_name', '')),
            'note' => trim((string) $this->configs->get('payment.qr_note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。')),
            'expireMinutes' => max(1, (int) $this->configs->get('payment.order_expire_minutes', 15)),
            'creditRate' => (string) $this->configs->get('payment.cny_to_credit_rate', '1'),
        ];
    }
}
