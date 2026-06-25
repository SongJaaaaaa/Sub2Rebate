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
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function config(): array
    {
        $enabled = (bool) $this->configs->get('payment.qr_enabled', true);
        $qrUrl = trim((string) $this->configs->get('payment.alipay_qr_url', ''));
        $displayName = trim((string) $this->configs->get('payment.alipay_display_name', ''));
        $note = trim((string) $this->configs->get('payment.qr_note', '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。'));
        $expireMinutes = max(1, (int) $this->configs->get('payment.order_expire_minutes', 15));

        return [
            'enabled' => $enabled,
            'channel' => 'alipay',
            'qrUrl' => $qrUrl,
            'displayName' => $displayName,
            'note' => $note,
            'expireMinutes' => $expireMinutes,
            'epayEnabled' => (bool) $this->configs->get('payment.epay_enabled', false),
        ];
    }

    public function create(User $user, array $data): array
    {
        $config = $this->config();
        if (! $config['enabled']) {
            return $this->fail('当前未开启二维码充值');
        }

        if ($config['qrUrl'] === '') {
            return $this->fail('支付宝二维码未配置');
        }

        $result = $this->createPendingOrder($user, $data['amount'] ?? null, 'alipay', (string) ($data['remark'] ?? ''));
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        return [
            'ok' => true,
            'order' => $this->payload($result['order'], $config),
        ];
    }

    /**
     * 创建一个待支付订单（通道无关），复用赠送/订单号/有效期逻辑。
     * 供人工二维码通道与 Epay 在线支付通道共用，避免赠送规则重复实现。
     *
     * @return array{ok:bool, order?:RechargeOrder, code?:int, message?:string, status?:int}
     */
    public function createPendingOrder(User $user, mixed $amountInput, string $channel, string $remark = ''): array
    {
        $amount = $this->amount($amountInput);
        if ($amount < 10) {
            return $this->fail('最低充值金额为 10 元');
        }

        $bonus = $this->bonus($amount);
        $credit = $this->amount($amount + $bonus);
        $expireAt = now()->addMinutes((int) $this->config()['expireMinutes']);

        $order = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => $this->orderNo(),
            'channel' => $channel,
            'amount' => $this->money($amount),
            'bonus_amount' => $this->money($bonus),
            'credit_amount' => $this->money($credit),
            'status' => RechargeOrder::STATUS_PENDING,
            'remark' => trim($remark),
            'expire_at' => $expireAt,
        ]);

        return [
            'ok' => true,
            'order' => $order,
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

    public function payload(RechargeOrder $order, ?array $config = null): array
    {
        $config ??= $this->config();

        return [
            'id' => (int) $order->id,
            'orderNo' => $order->order_no,
            'channel' => $order->channel,
            'amount' => $this->money2($order->amount),
            'bonusAmount' => $this->money2($order->bonus_amount),
            'creditAmount' => $this->money2($order->credit_amount),
            'status' => $order->status,
            'payerName' => (string) ($order->payer_name ?? ''),
            'payerAccount' => (string) ($order->payer_account ?? ''),
            'voucherImageUrl' => (string) ($order->voucher_image_url ?? ''),
            'remark' => (string) ($order->remark ?? ''),
            'reviewRemark' => (string) ($order->review_remark ?? ''),
            'rebateEventId' => $order->rebate_event_id,
            'submittedAt' => $this->time($order->submitted_at),
            'reviewedAt' => $this->time($order->reviewed_at),
            'paidAt' => $this->time($order->paid_at),
            'expireAt' => $this->time($order->expire_at),
            'createdAt' => $this->time($order->created_at),
            'qrUrl' => $config['qrUrl'],
            'displayName' => $config['displayName'],
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