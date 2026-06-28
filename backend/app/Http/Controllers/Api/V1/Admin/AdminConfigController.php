<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Services\EpayService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminConfigController extends Controller
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly EpayService $epay,
    )
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
        $mode = trim((string) $request->input('mode', 'manual_qr'));
        if (! in_array($mode, ['manual_qr', 'epay'], true)) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '支付通道模式不正确', null, 422);
        }

        $enabled = $request->boolean('enabled', true);
        $qrUrl = trim((string) $request->input('qrUrl', ''));
        $displayName = trim((string) $request->input('displayName', ''));
        $note = trim((string) $request->input('note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。'));
        $expireMinutes = (int) $request->input('expireMinutes', 15);
        $creditRate = trim((string) $request->input('creditRate', '1'));
        $rechargeName = trim((string) $request->input('rechargeName', '额度充值')) ?: '额度充值';
        $feeRate = trim((string) $request->input('feeRate', '0.6'));
        $epay = is_array($request->input('epay')) ? $request->input('epay') : [];
        $epayEnabled = (bool) ($epay['enabled'] ?? false);
        $epayPid = trim((string) ($epay['pid'] ?? ''));
        $epayKey = trim((string) ($epay['key'] ?? ''));
        $oldEpayKey = $this->cfg(['payment.epay.key', 'payment.alimpay.key']);
        $epayGatewayUrl = rtrim(trim((string) ($epay['gatewayUrl'] ?? '')), '/');
        $epayNotifyUrl = trim((string) ($epay['notifyUrl'] ?? ''));
        $epayReturnUrl = trim((string) ($epay['returnUrl'] ?? ''));
        $epayDisplayName = trim((string) ($epay['displayName'] ?? 'Epay 当面付'));
        $epaySitename = trim((string) ($epay['sitename'] ?? 'Sub2Rebate'));
        $epayType = trim((string) ($epay['type'] ?? 'alipay')) ?: 'alipay';
        $alipayTransfer = is_array($request->input('alipayTransfer')) ? $request->input('alipayTransfer') : [];
        $transferEnabled = (bool) ($alipayTransfer['enabled'] ?? false);
        $transferAutoPayEnabled = (bool) ($alipayTransfer['autoPayEnabled'] ?? false);
        $transferRetryEnabled = (bool) ($alipayTransfer['retryEnabled'] ?? false);
        $transferRetryInterval = trim((string) ($alipayTransfer['retryIntervalMinutes'] ?? '5'));
        $transferRetryBatch = trim((string) ($alipayTransfer['retryBatchSize'] ?? '50'));
        $transferGatewayUrl = rtrim(trim((string) ($alipayTransfer['gatewayUrl'] ?? 'https://openapi.alipay.com/gateway.do')), '/');
        $transferAppId = trim((string) ($alipayTransfer['appId'] ?? ''));
        $transferPrivateKey = trim((string) ($alipayTransfer['privateKey'] ?? ''));
        $oldTransferPrivateKey = trim((string) $this->configs->get('payment.alipay_transfer.private_key', ''));
        $transferPublicKey = trim((string) ($alipayTransfer['alipayPublicKey'] ?? ''));
        $oldTransferPublicKey = trim((string) $this->configs->get('payment.alipay_transfer.alipay_public_key', ''));
        $transferSingleMax = trim((string) ($alipayTransfer['singleMaxAmount'] ?? '500'));
        $transferDailyLimit = trim((string) ($alipayTransfer['dailyLimitAmount'] ?? '5000'));
        $transferIdentityType = trim((string) ($alipayTransfer['identityType'] ?? 'ALIPAY_LOGON_ID')) ?: 'ALIPAY_LOGON_ID';
        $transferOrderTitle = trim((string) ($alipayTransfer['orderTitle'] ?? '返利提现')) ?: '返利提现';
        $withdrawDailyLimit = (int) $request->input('withdrawDailyLimit', $this->configs->get('withdraw.daily_limit', 1));

        if ($enabled && $mode === 'manual_qr' && $qrUrl === '') {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '开启二维码充值时必须提供支付宝二维码', null, 422);
        }

        if ($expireMinutes < 1 || $expireMinutes > 1440) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '充值订单有效期必须在 1 到 1440 分钟之间', null, 422);
        }

        if (! is_numeric($creditRate) || (float) $creditRate <= 0) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '人民币额度换算比例必须大于 0', null, 422);
        }

        if (! is_numeric($feeRate) || (float) $feeRate < 0 || (float) $feeRate > 100) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '收款手续费比例必须在 0 到 100 之间', null, 422);
        }

        if ($enabled && $mode === 'epay') {
            foreach ([
                ['value' => $epayPid, 'name' => 'Epay 商户 PID'],
                ['value' => $epayKey !== '' ? $epayKey : $oldEpayKey, 'name' => 'Epay 商户 Key'],
                ['value' => $epayGatewayUrl, 'name' => 'Epay 地址'],
                ['value' => $epayNotifyUrl, 'name' => 'Epay 回调地址'],
                ['value' => $epayReturnUrl, 'name' => 'Epay 返回地址'],
            ] as $item) {
                if ($item['value'] === '') {
                    return ApiResponse::fail(ApiError::VALIDATION_FAILED, $item['name'].'不能为空', null, 422);
                }
            }
        }

        if ($transferEnabled) {
            foreach ([
                ['value' => $transferGatewayUrl, 'name' => '支付宝转账网关'],
                ['value' => $transferAppId, 'name' => '支付宝应用 ID'],
                ['value' => $transferPrivateKey !== '' ? $transferPrivateKey : $oldTransferPrivateKey, 'name' => '支付宝应用私钥'],
            ] as $item) {
                if ($item['value'] === '') {
                    return ApiResponse::fail(ApiError::VALIDATION_FAILED, $item['name'].'不能为空', null, 422);
                }
            }
        }

        if (! in_array($transferIdentityType, ['ALIPAY_LOGON_ID', 'ALIPAY_USER_ID'], true)) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '支付宝收款标识类型不正确', null, 422);
        }

        if (! is_numeric($transferSingleMax) || (float) $transferSingleMax < 0) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '支付宝单笔打款限额不能小于 0', null, 422);
        }

        if (! is_numeric($transferDailyLimit) || (float) $transferDailyLimit < 0) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '支付宝每日打款限额不能小于 0', null, 422);
        }

        if (! is_numeric($transferRetryInterval) || (int) $transferRetryInterval < 1) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '打款重试间隔不能小于 1 分钟', null, 422);
        }

        if (! is_numeric($transferRetryBatch) || (int) $transferRetryBatch < 1 || (int) $transferRetryBatch > 500) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '打款重试批量必须在 1 到 500 之间', null, 422);
        }

        if ($withdrawDailyLimit < 1 || $withdrawDailyLimit > 100) {
            return ApiResponse::fail(ApiError::VALIDATION_FAILED, '每日支付宝提现次数必须在 1 到 100 之间', null, 422);
        }

        $this->configs->updateBatch([
            'payment.mode' => $mode,
            'payment.qr_enabled' => $enabled,
            'payment.alipay_qr_url' => $qrUrl,
            'payment.alipay_display_name' => $displayName,
            'payment.qr_note' => $note,
            'payment.order_expire_minutes' => $expireMinutes,
            'payment.cny_to_credit_rate' => $creditRate,
            'payment.recharge_name' => $rechargeName,
            'payment.recharge_fee_rate' => $feeRate,
            'payment.epay.enabled' => $epayEnabled,
            'payment.epay.pid' => $epayPid,
            'payment.epay.key' => $epayKey !== '' ? $epayKey : $oldEpayKey,
            'payment.epay.gateway_url' => $epayGatewayUrl,
            'payment.epay.notify_url' => $epayNotifyUrl,
            'payment.epay.return_url' => $epayReturnUrl,
            'payment.epay.display_name' => $epayDisplayName,
            'payment.epay.sitename' => $epaySitename,
            'payment.epay.type' => $epayType,
            'payment.alipay_transfer.enabled' => $transferEnabled,
            'payment.alipay_transfer.auto_pay_enabled' => $transferAutoPayEnabled,
            'payment.alipay_transfer.retry_enabled' => $transferRetryEnabled,
            'payment.alipay_transfer.retry_interval_minutes' => (int) $transferRetryInterval,
            'payment.alipay_transfer.retry_batch_size' => (int) $transferRetryBatch,
            'payment.alipay_transfer.gateway_url' => $transferGatewayUrl,
            'payment.alipay_transfer.app_id' => $transferAppId,
            'payment.alipay_transfer.private_key' => $transferPrivateKey !== '' ? $transferPrivateKey : $oldTransferPrivateKey,
            'payment.alipay_transfer.alipay_public_key' => $transferPublicKey !== '' ? $transferPublicKey : $oldTransferPublicKey,
            'payment.alipay_transfer.single_max_amount' => $transferSingleMax,
            'payment.alipay_transfer.daily_limit_amount' => $transferDailyLimit,
            'payment.alipay_transfer.identity_type' => $transferIdentityType,
            'payment.alipay_transfer.order_title' => $transferOrderTitle,
            'withdraw.daily_limit' => $withdrawDailyLimit,
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
            'rebate.stage_amount' => ['name' => '多级返利门槛', 'type' => 'number', 'min' => 0.01],
            'rebate.stage_reward_amount' => ['name' => '每次分配奖励池', 'type' => 'number', 'min' => 0],
            'rebate.decay_factor' => ['name' => '衰减系数', 'type' => 'number', 'min' => 0.000001, 'max' => 1],
            'rebate.max_depth' => ['name' => '最大返利深度', 'type' => 'number', 'min' => 1, 'max' => 20],
            'rebate.inactive_node_mode' => ['name' => '失效节点返利处理方式', 'type' => 'string', 'in' => ['platform', 'exclude_recalculate']],
            'rebate.recharge_bonus_100' => ['name' => '充值 100 赠送金额', 'type' => 'number', 'min' => 0],
            'rebate.recharge_bonus_200' => ['name' => '充值 200 赠送金额', 'type' => 'number', 'min' => 0],
            'rebate.recharge_bonus_500' => ['name' => '充值 500 赠送金额', 'type' => 'number', 'min' => 0],
            'rebate.recharge_bonus_1000' => ['name' => '充值 1000 赠送金额', 'type' => 'number', 'min' => 0],
            'payment.cny_to_credit_rate' => ['name' => '人民币额度换算比例', 'type' => 'number', 'min' => 0.000001],
            'payment.recharge_fee_rate' => ['name' => '收款手续费比例', 'type' => 'number', 'min' => 0, 'max' => 100],
            'payment.mode' => ['name' => '支付通道模式', 'type' => 'string', 'in' => ['manual_qr', 'epay']],
            'payment.order_expire_minutes' => ['name' => '充值订单有效期', 'type' => 'number', 'min' => 1, 'max' => 1440],
            'payment.alipay_transfer.single_max_amount' => ['name' => '支付宝单笔打款限额', 'type' => 'number', 'min' => 0],
            'payment.alipay_transfer.daily_limit_amount' => ['name' => '支付宝每日打款限额', 'type' => 'number', 'min' => 0],
            'payment.alipay_transfer.identity_type' => ['name' => '支付宝收款标识类型', 'type' => 'string', 'in' => ['ALIPAY_LOGON_ID', 'ALIPAY_USER_ID']],
            'payment.alipay_transfer.retry_interval_minutes' => ['name' => '打款重试间隔', 'type' => 'number', 'min' => 1, 'max' => 1440],
            'payment.alipay_transfer.retry_batch_size' => ['name' => '打款重试批量', 'type' => 'number', 'min' => 1, 'max' => 500],
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
        $rawMode = trim((string) $this->configs->get('payment.mode', 'manual_qr'));
        $mode = in_array($rawMode, ['epay', 'alimpay_qr'], true) ? 'epay' : 'manual_qr';
        $epay = $this->epay->config();

        return [
            'enabled' => (bool) $this->configs->get('payment.qr_enabled', true),
            'mode' => $mode,
            'channel' => $mode === 'epay' ? 'epay' : 'alipay',
            'qrUrl' => trim((string) $this->configs->get('payment.alipay_qr_url', '')),
            'displayName' => trim((string) $this->configs->get('payment.alipay_display_name', '')),
            'note' => trim((string) $this->configs->get('payment.qr_note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。')),
            'expireMinutes' => max(1, (int) $this->configs->get('payment.order_expire_minutes', 15)),
            'creditRate' => (string) $this->configs->get('payment.cny_to_credit_rate', '1'),
            'rechargeName' => trim((string) $this->configs->get('payment.recharge_name', '额度充值')) ?: '额度充值',
            'feeRate' => (string) $this->configs->get('payment.recharge_fee_rate', '0.6'),
            'withdrawDailyLimit' => max(1, min((int) $this->configs->get('withdraw.daily_limit', 1), 100)),
            'epay' => [
                'enabled' => $epay['enabled'],
                'pid' => $epay['pid'],
                'key' => '',
                'hasKey' => $epay['key'] !== '',
                'gatewayUrl' => $epay['gatewayUrl'],
                'notifyUrl' => $epay['notifyUrl'],
                'returnUrl' => $epay['returnUrl'],
                'displayName' => $epay['displayName'],
                'sitename' => $epay['sitename'],
                'type' => $epay['type'],
            ],
            'alipayTransfer' => [
                'enabled' => (bool) $this->configs->get('payment.alipay_transfer.enabled', false),
                'autoPayEnabled' => (bool) $this->configs->get('payment.alipay_transfer.auto_pay_enabled', false),
                'retryEnabled' => (bool) $this->configs->get('payment.alipay_transfer.retry_enabled', false),
                'retryIntervalMinutes' => max(1, (int) $this->configs->get('payment.alipay_transfer.retry_interval_minutes', 5)),
                'retryBatchSize' => max(1, min((int) $this->configs->get('payment.alipay_transfer.retry_batch_size', 50), 500)),
                'gatewayUrl' => $this->cfg('payment.alipay_transfer.gateway_url', 'ALIPAY_TRANSFER_GATEWAY', 'https://openapi.alipay.com/gateway.do'),
                'appId' => $this->cfg('payment.alipay_transfer.app_id', 'ALIPAY_TRANSFER_APP_ID'),
                'privateKey' => '',
                'hasPrivateKey' => $this->cfg('payment.alipay_transfer.private_key', 'ALIPAY_TRANSFER_PRIVATE_KEY') !== '',
                'alipayPublicKey' => '',
                'hasAlipayPublicKey' => $this->cfg('payment.alipay_transfer.alipay_public_key', 'ALIPAY_TRANSFER_PUBLIC_KEY') !== '',
                'singleMaxAmount' => (string) $this->configs->get('payment.alipay_transfer.single_max_amount', '500'),
                'dailyLimitAmount' => (string) $this->configs->get('payment.alipay_transfer.daily_limit_amount', '5000'),
                'identityType' => $this->cfg('payment.alipay_transfer.identity_type', 'ALIPAY_TRANSFER_IDENTITY_TYPE', 'ALIPAY_LOGON_ID') ?: 'ALIPAY_LOGON_ID',
                'orderTitle' => trim((string) $this->configs->get('payment.alipay_transfer.order_title', '返利提现')),
            ],
        ];
    }

    private function cfg(string|array $keys, string $env = '', string $default = ''): string
    {
        foreach ((array) $keys as $key) {
            $value = trim((string) $this->configs->get($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $env !== '' ? trim((string) env($env, $default)) : $default;
    }
}
