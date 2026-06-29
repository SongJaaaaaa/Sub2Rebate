<?php

namespace App\Modules\Payment\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use App\Support\ApiError;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class RechargeOrderService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly EpayService $epay,
    ) {
    }

    public function config(): array
    {
        $rawMode = trim((string) $this->configs->get('payment.mode', 'manual_qr'));
        $mode = in_array($rawMode, [RechargeOrder::CHANNEL_EPAY, 'alimpay_qr'], true)
            ? RechargeOrder::CHANNEL_EPAY
            : 'manual_qr';
        $enabled = (bool) $this->configs->get('payment.qr_enabled', true);
        $qrUrl = trim((string) $this->configs->get('payment.alipay_qr_url', ''));
        $displayName = trim((string) $this->configs->get('payment.alipay_display_name', ''));
        $note = trim((string) $this->configs->get('payment.qr_note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。'));
        $expireMinutes = max(1, (int) $this->configs->get('payment.order_expire_minutes', 15));
        $epay = $this->epay->config();
        $channel = $mode === RechargeOrder::CHANNEL_EPAY
            ? RechargeOrder::CHANNEL_EPAY
            : RechargeOrder::CHANNEL_ALIPAY;

        return [
            'enabled' => $mode === RechargeOrder::CHANNEL_EPAY ? $enabled && $epay['enabled'] : $enabled,
            'mode' => $mode,
            'channel' => $channel,
            'qrUrl' => $qrUrl,
            'displayName' => $mode === RechargeOrder::CHANNEL_EPAY ? $epay['displayName'] : $displayName,
            'note' => $mode === RechargeOrder::CHANNEL_EPAY ? '创建订单后将跳转到 Epay 支付页面，支付成功后自动入账。' : $note,
            'expireMinutes' => $expireMinutes,
            'epay' => [
                'enabled' => $epay['enabled'],
                'displayName' => $epay['displayName'],
                'gatewayUrl' => $epay['gatewayUrl'],
                'notifyUrl' => $epay['notifyUrl'],
                'returnUrl' => $epay['returnUrl'],
                'pid' => $epay['pid'],
                'type' => $epay['type'],
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

        if ($config['mode'] === RechargeOrder::CHANNEL_EPAY) {
            $error = $this->epay->canCreate();
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
            'out_trade_no' => $config['mode'] === RechargeOrder::CHANNEL_EPAY ? $orderNo : null,
            'subject' => $subject,
            'amount' => $this->money($amount),
            'bonus_amount' => $this->money($bonus),
            'credit_amount' => $this->money($credit),
            'status' => RechargeOrder::STATUS_PENDING,
            'credit_status' => RechargeOrder::CREDIT_PENDING,
            'remark' => trim((string) ($data['remark'] ?? '')),
            'expire_at' => $expireAt,
        ]);

        if ($config['mode'] === RechargeOrder::CHANNEL_EPAY) {
            try {
                $epayOrder = $this->epay->createOrder($order, request()->ip() ?: '127.0.0.1');
            } catch (\Throwable $e) {
                $order->status = RechargeOrder::STATUS_FAILED;
                $order->credit_status = RechargeOrder::CREDIT_FAILED;
                $order->credit_fail_msg = $e->getMessage();
                $order->save();

                return $this->fail($e->getMessage());
            }

            $order->pay_url = $epayOrder['payUrl'];
            $order->provider_trade_no = $epayOrder['tradeNo'] ?: null;
            $order->channel_config_snapshot = $this->epay->snapshot();
            $order->notify_payload = ['epay_create' => $epayOrder['raw']];
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

        if ($order->channel === RechargeOrder::CHANNEL_EPAY) {
            return $this->fail('Epay 订单会自动回调入账，无需提交付款信息');
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

    public function list(User $user, int $page = 1, int $pageSize = 20, string $status = '', string $startDate = '', string $endDate = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 100));

        $query = RechargeOrder::query()->where('user_id', $user->id);
        $quotaQuery = AuditLog::query()
            ->where('module', 'sub2api')
            ->where('action', 'sub2api.api_quota_adjust')
            ->where('target_user_id', $user->id);
        if ($status !== '') {
            $query->where('status', $status);
            if ($status !== RechargeOrder::STATUS_APPROVED) {
                $quotaQuery->whereRaw('1 = 0');
            }
        }
        if ($startDate !== '') {
            $start = CarbonImmutable::parse($startDate, 'Asia/Shanghai')->startOfDay();
            $query->where('created_at', '>=', $start);
            $quotaQuery->where('created_at', '>=', $start);
        }
        if ($endDate !== '') {
            $end = CarbonImmutable::parse($endDate, 'Asia/Shanghai')->endOfDay();
            $query->where('created_at', '<=', $end);
            $quotaQuery->where('created_at', '<=', $end);
        }

        $config = $this->config();
        $items = [
            ...$query->get()->map(fn (RechargeOrder $row): array => $this->payload($row, $config))->all(),
            ...$quotaQuery->get()->map(fn (AuditLog $log): array => $this->apiQuotaPayload($log))->all(),
        ];
        usort($items, fn (array $a, array $b): int => strcmp((string) $b['createdAt'], (string) $a['createdAt']));

        return [
            'list' => array_slice($items, ($page - 1) * $pageSize, $pageSize),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => count($items),
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
            'recordId' => 'order-'.$order->id,
            'source' => 'sub2rebate',
            'sourceLabel' => '返利系统',
            'type' => 'add',
            'orderNo' => $order->order_no,
            'channel' => $order->channel,
            'channelLabel' => $order->channel === RechargeOrder::CHANNEL_EPAY ? 'Epay' : '支付宝',
            'outTradeNo' => (string) ($order->out_trade_no ?? ''),
            'providerTradeNo' => (string) ($order->provider_trade_no ?? ''),
            'subject' => (string) ($order->subject ?? ''),
            'amount' => $this->money2($order->amount),
            'bonusAmount' => $this->money2($order->bonus_amount),
            'creditAmount' => $this->money2($order->credit_amount),
            'paidAmount' => $order->paid_amount === null ? '' : $this->money2($order->paid_amount),
            'sub2BalanceBefore' => $order->sub2_balance_before === null ? '' : $this->money2($order->sub2_balance_before),
            'sub2BalanceAfter' => $order->sub2_balance_after === null ? '' : $this->money2($order->sub2_balance_after),
            'status' => $order->status,
            'tradeStatus' => (string) ($order->trade_status ?? ''),
            'creditStatus' => (string) ($order->credit_status ?? RechargeOrder::CREDIT_PENDING),
            'payerName' => (string) ($order->payer_name ?? ''),
            'payerAccount' => (string) ($order->payer_account ?? ''),
            'voucherImageUrl' => (string) ($order->voucher_image_url ?? ''),
            'remark' => (string) ($order->remark ?? ''),
            'reviewRemark' => (string) ($order->review_remark ?? ''),
            'creditFailMsg' => (string) ($order->credit_fail_msg ?? ''),
            'reason' => '在线充值',
            'operator' => '用户',
            'rebateEnabled' => $order->rebate_event_id !== null,
            'rebateEventId' => $order->rebate_event_id,
            'submittedAt' => $this->time($order->submitted_at),
            'reviewedAt' => $this->time($order->reviewed_at),
            'paidAt' => $this->time($order->paid_at),
            'creditedAt' => $this->time($order->credited_at),
            'expireAt' => $this->time($order->expire_at),
            'createdAt' => $this->time($order->created_at),
            'payUrl' => (string) ($order->pay_url ?? ''),
            'qrUrl' => $config['qrUrl'],
            'displayName' => $order->channel === RechargeOrder::CHANNEL_EPAY ? $config['displayName'] : $config['displayName'],
            'note' => $order->rebate_event_id !== null ? '本次充值计入返利。' : $config['note'],
        ];
    }

    private function apiQuotaPayload(AuditLog $log): array
    {
        $after = is_array($log->after_values) ? $log->after_values : [];
        $type = (string) ($after['operation'] ?? 'add');
        $amount = $this->money2($after['amount'] ?? 0);
        $rebateEnabled = (bool) ($after['rebate_enabled'] ?? ($after['rebate_event_id'] ?? null) !== null);

        return [
            'id' => -1 * (int) $log->id,
            'recordId' => 'audit-'.$log->id,
            'source' => 'sub2rebate',
            'sourceLabel' => '返利系统',
            'type' => $type === 'subtract' ? 'subtract' : 'add',
            'orderNo' => 'API-'.$log->id,
            'channel' => 'api_quota',
            'channelLabel' => '管理员调整',
            'outTradeNo' => '',
            'providerTradeNo' => '',
            'subject' => $type === 'subtract' ? 'API额度扣减' : 'API额度增加',
            'amount' => $amount,
            'bonusAmount' => '0.00',
            'creditAmount' => $type === 'subtract' ? '0.00' : $amount,
            'paidAmount' => '',
            'payableAmount' => $amount,
            'sub2BalanceBefore' => '',
            'sub2BalanceAfter' => '',
            'status' => RechargeOrder::STATUS_APPROVED,
            'tradeStatus' => '',
            'creditStatus' => RechargeOrder::CREDIT_SUCCESS,
            'payerName' => '',
            'payerAccount' => '',
            'voucherImageUrl' => '',
            'remark' => (string) ($log->remark ?: ''),
            'reviewRemark' => '',
            'creditFailMsg' => '',
            'reason' => (string) ($after['reason'] ?? '额度调整'),
            'operator' => $log->actor_user_id !== null ? '管理员#'.$log->actor_user_id : '系统',
            'rebateEnabled' => $rebateEnabled,
            'rebateEventId' => $after['rebate_event_id'] ?? null,
            'submittedAt' => '',
            'reviewedAt' => '',
            'paidAt' => $this->time($log->created_at),
            'creditedAt' => $this->time($log->created_at),
            'expireAt' => '',
            'createdAt' => $this->time($log->created_at),
            'payUrl' => '',
            'qrUrl' => '',
            'displayName' => 'Sub2API 额度',
            'note' => $rebateEnabled ? '本次额度调整计入充值返利。' : '本次额度调整未计入充值返利。',
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
