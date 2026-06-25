<?php

namespace App\Modules\Payment\Services;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use App\Support\ApiError;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class RechargeOrderService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly AliMPayService $aliMPay,
    ) {
    }

    public function config(): array
    {
        $mode = trim((string) $this->configs->get('payment.mode', 'manual_qr'));
        $mode = $mode === RechargeOrder::CHANNEL_ALIMPAY_QR ? RechargeOrder::CHANNEL_ALIMPAY_QR : 'manual_qr';
        $enabled = (bool) $this->configs->get('payment.qr_enabled', true);
        $qrUrl = trim((string) $this->configs->get('payment.alipay_qr_url', ''));
        $displayName = trim((string) $this->configs->get('payment.alipay_display_name', ''));
        $note = trim((string) $this->configs->get('payment.qr_note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。'));
        $expireMinutes = max(1, (int) $this->configs->get('payment.order_expire_minutes', 15));
        $alimpay = $this->aliMPay->config();
        $channel = $mode === RechargeOrder::CHANNEL_ALIMPAY_QR
            ? RechargeOrder::CHANNEL_ALIMPAY_QR
            : RechargeOrder::CHANNEL_ALIPAY;

        return [
            'enabled' => $mode === RechargeOrder::CHANNEL_ALIMPAY_QR ? $enabled && $alimpay['enabled'] : $enabled,
            'mode' => $mode,
            'channel' => $channel,
            'qrUrl' => $qrUrl,
            'displayName' => $mode === RechargeOrder::CHANNEL_ALIMPAY_QR ? $alimpay['displayName'] : $displayName,
            'note' => $mode === RechargeOrder::CHANNEL_ALIMPAY_QR ? '创建订单后将跳转到支付页面，支付成功后自动入账。' : $note,
            'expireMinutes' => $expireMinutes,
            'alimpay' => [
                'enabled' => $alimpay['enabled'],
                'displayName' => $alimpay['displayName'],
                'gatewayUrl' => $alimpay['gatewayUrl'],
                'notifyUrl' => $alimpay['notifyUrl'],
                'returnUrl' => $alimpay['returnUrl'],
                'pid' => $alimpay['pid'],
            ],
        ];
    }

    public function create(User $user, array $data): array
    {
        $config = $this->config();
        if (! $config['enabled']) {
            return $this->fail('当前未开启充值通道');
        }

        if ($config['mode'] === 'manual_qr' && $config['qrUrl'] === '') {
            return $this->fail('支付宝二维码未配置');
        }

        if ($config['mode'] === RechargeOrder::CHANNEL_ALIMPAY_QR) {
            $error = $this->aliMPay->canCreate();
            if ($error !== null) {
                return $this->fail($error);
            }
        }

        $amount = $this->amount($data['amount'] ?? null);
        if ($amount <= 0) {
            return $this->fail('充值金额必须大于 0');
        }

        $bonus = $this->bonus($amount);
        $credit = $this->amount($amount + $bonus);
        $expireAt = now()->addMinutes((int) $config['expireMinutes']);
        $orderNo = $this->orderNo();
        $subject = 'API充值-'.$this->money2($amount).'元';

        $order = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => $orderNo,
            'channel' => $config['channel'],
            'out_trade_no' => $config['mode'] === RechargeOrder::CHANNEL_ALIMPAY_QR ? $orderNo : null,
            'subject' => $subject,
            'amount' => $this->money($amount),
            'bonus_amount' => $this->money($bonus),
            'credit_amount' => $this->money($credit),
            'status' => RechargeOrder::STATUS_PENDING,
            'credit_status' => RechargeOrder::CREDIT_PENDING,
            'remark' => trim((string) ($data['remark'] ?? '')),
            'expire_at' => $expireAt,
        ]);

        if ($config['mode'] === RechargeOrder::CHANNEL_ALIMPAY_QR) {
            $order->pay_url = $this->aliMPay->payUrl($order);
            $order->channel_config_snapshot = $this->aliMPay->snapshot();
            $order->save();
        }

        return [
            'ok' => true,
            'order' => $this->payload($order, $config),
        ];
    }

    public function submit(User $user, RechargeOrder $order, array $data): array
    {
        if ((int) $order->user_id !== (int) $user->id) {
            return [
                'ok' => false,
                'code' => ApiError::FORBIDDEN,
                'message' => '不能操作别人的充值订单',
                'status' => 403,
            ];
        }

        if ($order->status !== RechargeOrder::STATUS_PENDING) {
            return $this->fail('当前订单状态不能提交支付');
        }

        if ($order->channel === RechargeOrder::CHANNEL_ALIMPAY_QR) {
            return $this->fail('扫码支付订单会自动回调入账，无需提交付款信息');
        }

        if ($order->expire_at instanceof CarbonInterface && $order->expire_at->isPast()) {
            $order->status = RechargeOrder::STATUS_EXPIRED;
            $order->save();

            return $this->fail('订单已过期，请重新创建');
        }

        $payerName = trim((string) ($data['payerName'] ?? ''));
        $payerAccount = trim((string) ($data['payerAccount'] ?? ''));
        if ($payerName === '' || $payerAccount === '') {
            return $this->fail('付款姓名和付款账号不能为空');
        }

        $order->payer_name = $payerName;
        $order->payer_account = $payerAccount;
        $order->voucher_image_url = trim((string) ($data['voucherImageUrl'] ?? ''));
        $order->submitted_at = now();
        $order->status = RechargeOrder::STATUS_SUBMITTED;
        $order->save();

        return [
            'ok' => true,
            'order' => $this->payload($order, $this->config()),
        ];
    }

    public function list(User $user, int $page = 1, int $pageSize = 20, string $status = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 100));

        $query = RechargeOrder::query()->where('user_id', $user->id);
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();
        $config = $this->config();

        return [
            'list' => $rows->map(fn (RechargeOrder $row): array => $this->payload($row, $config))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    public function show(User $user, RechargeOrder $order): array
    {
        if ((int) $order->user_id !== (int) $user->id) {
            return [
                'ok' => false,
                'code' => ApiError::FORBIDDEN,
                'message' => '不能查看别人的充值订单',
                'status' => 403,
            ];
        }

        return [
            'ok' => true,
            'order' => $this->payload($order),
        ];
    }

    public function payload(RechargeOrder $order, ?array $config = null): array
    {
        $config ??= $this->config();

        return [
            'id' => (int) $order->id,
            'orderNo' => $order->order_no,
            'channel' => $order->channel,
            'outTradeNo' => (string) ($order->out_trade_no ?? ''),
            'providerTradeNo' => (string) ($order->provider_trade_no ?? ''),
            'subject' => (string) ($order->subject ?? ''),
            'amount' => $this->money2($order->amount),
            'bonusAmount' => $this->money2($order->bonus_amount),
            'creditAmount' => $this->money2($order->credit_amount),
            'paidAmount' => $order->paid_amount === null ? '' : $this->money2($order->paid_amount),
            'status' => $order->status,
            'tradeStatus' => (string) ($order->trade_status ?? ''),
            'creditStatus' => (string) ($order->credit_status ?? RechargeOrder::CREDIT_PENDING),
            'payerName' => (string) ($order->payer_name ?? ''),
            'payerAccount' => (string) ($order->payer_account ?? ''),
            'voucherImageUrl' => (string) ($order->voucher_image_url ?? ''),
            'remark' => (string) ($order->remark ?? ''),
            'reviewRemark' => (string) ($order->review_remark ?? ''),
            'creditFailMsg' => (string) ($order->credit_fail_msg ?? ''),
            'rebateEventId' => $order->rebate_event_id,
            'submittedAt' => $this->time($order->submitted_at),
            'reviewedAt' => $this->time($order->reviewed_at),
            'paidAt' => $this->time($order->paid_at),
            'creditedAt' => $this->time($order->credited_at),
            'expireAt' => $this->time($order->expire_at),
            'createdAt' => $this->time($order->created_at),
            'payUrl' => (string) ($order->pay_url ?? ''),
            'qrUrl' => $config['qrUrl'],
            'displayName' => $order->channel === RechargeOrder::CHANNEL_ALIMPAY_QR ? '商家' : $config['displayName'],
            'note' => $config['note'],
        ];
    }

    private function bonus(float $amount): float
    {
        return match (true) {
            $amount >= 1000 => 120.0,
            $amount >= 500 => 50.0,
            $amount >= 200 => 15.0,
            $amount >= 100 => 5.0,
            default => 0.0,
        };
    }

    private function orderNo(): string
    {
        return 'RC'.now()->format('YmdHisv').Str::upper(Str::random(4));
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 6) : 0.0;
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function money2(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
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

    private function fail(string $message): array
    {
        return [
            'ok' => false,
            'code' => ApiError::BAD_REQUEST,
            'message' => $message,
            'status' => 400,
        ];
    }
}
