<?php

namespace App\Modules\Payment\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Payment\Models\PaymentNotifyLog;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;
use Throwable;

class RechargeCallbackService
{
    public function __construct(
        private readonly EpayService $epay,
        private readonly AuditLogService $audits,
        private readonly Sub2ApiAdminClient $sub2Api,
        private readonly RechargeEventService $recharges,
    ) {
    }

    public function handleEpay(array $payload): array
    {
        $log = PaymentNotifyLog::query()->create([
            'provider' => 'epay',
            'event_type' => 'trade_notify',
            'out_trade_no' => $this->str($payload['out_trade_no'] ?? ''),
            'provider_trade_no' => $this->str($payload['trade_no'] ?? ''),
            'notify_id' => $this->str($payload['notify_id'] ?? ''),
            'trade_status' => $this->str($payload['trade_status'] ?? ''),
            'verify_passed' => false,
            'handle_status' => 'pending',
            'payload' => $payload,
            'received_at' => now(),
        ]);

        foreach (['pid', 'trade_no', 'out_trade_no', 'type', 'name', 'money', 'trade_status', 'sign'] as $field) {
            if ($this->str($payload[$field] ?? '') === '') {
                return $this->finish($log, false, 'failed', '缺少回调参数 '.$field);
            }
        }

        if (! $this->epay->verify($payload)) {
            return $this->finish($log, false, 'failed', 'Epay 签名或商户 PID 校验失败');
        }

        $log->verify_passed = true;
        $log->save();

        if ($this->str($payload['type'] ?? '') !== 'alipay') {
            return $this->finish($log, false, 'failed', 'Epay 支付类型不匹配');
        }

        if ($this->str($payload['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return $this->finish($log, true, 'ignored', '非支付成功状态，已忽略');
        }

        $order = RechargeOrder::query()
            ->where('out_trade_no', $this->str($payload['out_trade_no'] ?? ''))
            ->first();

        if (! $order instanceof RechargeOrder) {
            return $this->finish($log, false, 'failed', '本地充值订单不存在');
        }

        if ($order->channel !== RechargeOrder::CHANNEL_EPAY) {
            return $this->finish($log, false, 'failed', '订单支付通道不匹配');
        }

        if ((float) $this->money2($payload['money'] ?? null) < (float) $this->money2($order->amount)) {
            return $this->finish($log, false, 'failed', '订单金额大于回调金额');
        }

        return $this->credit($order, $payload, $log);
    }

    public function syncEpayReturn(User $user, array $payload): array
    {
        $outTradeNo = $this->str($payload['out_trade_no'] ?? '');
        if ($outTradeNo === '') {
            return $this->fail('缺少回跳订单号');
        }

        $order = RechargeOrder::query()
            ->where('out_trade_no', $outTradeNo)
            ->first();

        if (! $order instanceof RechargeOrder) {
            return $this->fail('本地充值订单不存在');
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return [
                'ok' => false,
                'code' => ApiError::FORBIDDEN,
                'message' => '不能同步别人的充值订单',
                'status' => 403,
            ];
        }

        return $this->handleEpay($payload);
    }

    public function retryCredit(RechargeOrder $order, ?User $admin = null): array
    {
        if ($order->channel !== RechargeOrder::CHANNEL_EPAY) {
            return $this->fail('只有 Epay 订单可以重试入账');
        }

        if (! in_array($order->status, [RechargeOrder::STATUS_PAID, RechargeOrder::STATUS_FAILED], true)) {
            return $this->fail('只有已支付但未成功入账的订单可以重试');
        }

        if ($order->credit_status === RechargeOrder::CREDIT_SUCCESS) {
            return ['ok' => true, 'order' => $order];
        }

        $payload = is_array($order->notify_payload) ? $order->notify_payload : [];
        $log = PaymentNotifyLog::query()->create([
            'provider' => 'epay',
            'event_type' => 'credit_retry',
            'out_trade_no' => (string) $order->out_trade_no,
            'provider_trade_no' => (string) $order->provider_trade_no,
            'trade_status' => (string) $order->trade_status,
            'verify_passed' => true,
            'handle_status' => 'pending',
            'payload' => ['admin_user_id' => $admin?->id, 'source' => 'admin_retry'],
            'received_at' => now(),
        ]);

        $result = $this->credit($order, $payload, $log, '-retry-'.$log->id);
        if (($result['ok'] ?? false) && $admin instanceof User) {
            $this->audits->record('payment', 'payment.epay_credit_retry', [
                'actor' => $admin,
                'target_user_id' => $order->user_id,
                'subject_type' => RechargeOrder::class,
                'subject_id' => $order->id,
                'after_values' => $order->refresh()->toArray(),
                'remark' => 'Epay 手动重试入账',
            ]);
        }

        return $result;
    }

    private function credit(RechargeOrder $order, array $payload, PaymentNotifyLog $log, string $keySuffix = ''): array
    {
        return DB::transaction(function () use ($order, $payload, $log, $keySuffix): array {
            $locked = RechargeOrder::query()->lockForUpdate()->find($order->id);
            if (! $locked instanceof RechargeOrder) {
                return $this->finish($log, false, 'failed', '本地充值订单不存在');
            }

            if ($locked->status === RechargeOrder::STATUS_APPROVED && $locked->credit_status === RechargeOrder::CREDIT_SUCCESS) {
                return $this->finish($log, true, 'processed', '订单已入账，重复通知已忽略', $locked);
            }

            $before = $locked->toArray();
            $paidAt = now();

            $locked->status = RechargeOrder::STATUS_PAID;
            $locked->trade_status = $this->str($payload['trade_status'] ?? $locked->trade_status ?: 'TRADE_SUCCESS');
            $locked->provider_trade_no = $this->str($payload['trade_no'] ?? $locked->provider_trade_no);
            $locked->paid_amount = $this->money($payload['money'] ?? $locked->amount);
            $locked->paid_at ??= $paidAt;
            $locked->notify_payload = $payload ?: $locked->notify_payload;
            $locked->credit_status = RechargeOrder::CREDIT_PENDING;
            $locked->credit_fail_msg = null;
            $locked->sub2_balance_before ??= $this->currentSub2Balance($locked->user_id);
            $locked->save();

            try {
                $sub2Res = $this->sub2Api->updateUserBalance(
                    $locked->user_id,
                    (float) $locked->credit_amount,
                    'add',
                    'Epay充值 '.$locked->order_no,
                    'sub2rebate-recharge-order-'.$locked->order_no.$keySuffix
                );
                $locked->sub2_balance_after = $this->balanceFromResponse($sub2Res)
                    ?? $this->currentSub2Balance($locked->user_id);
            } catch (Throwable $e) {
                $locked->status = RechargeOrder::STATUS_FAILED;
                $locked->credit_status = RechargeOrder::CREDIT_FAILED;
                $locked->credit_fail_msg = $e->getMessage();
                $locked->save();

                return $this->finish($log, false, 'failed', $e->getMessage(), $locked);
            }

            $sourceId = 'recharge-order-'.$locked->order_no;
            $created = $this->recharges->createRechargeEvent([
                'user_id' => $locked->user_id,
                'source_type' => 'sub2rebate.recharge_order',
                'source_id' => $sourceId,
                'source_amount' => $this->money($locked->amount),
                'source_currency' => 'CNY',
                'credit_amount' => $this->money($locked->credit_amount),
                'operator_user_id' => null,
                'remark' => 'Epay 当面付充值到账',
                'occurred_at' => $locked->paid_at ?: $paidAt,
            ]);

            if (! ($created['ok'] ?? false)) {
                $locked->status = RechargeOrder::STATUS_FAILED;
                $locked->credit_status = RechargeOrder::CREDIT_FAILED;
                $locked->credit_fail_msg = (string) ($created['message'] ?? '创建充值事件失败');
                $locked->save();

                return $this->finish($log, false, 'failed', $locked->credit_fail_msg, $locked);
            }

            $locked->status = RechargeOrder::STATUS_APPROVED;
            $locked->credit_status = RechargeOrder::CREDIT_SUCCESS;
            $locked->credited_at = now();
            $locked->rebate_event_id = $created['rebateEvent']->id ?? null;
            $locked->credit_fail_msg = null;
            $locked->save();

            $this->audits->record('payment', 'payment.epay_notify_success', [
                'target_user_id' => $locked->user_id,
                'subject_type' => RechargeOrder::class,
                'subject_id' => $locked->id,
                'before_values' => $before,
                'after_values' => $locked->toArray() + ['sub2api_response' => $sub2Res],
                'remark' => 'Epay 回调自动入账',
            ]);

            return $this->finish($log, true, 'processed', 'Epay 回调入账成功', $locked);
        });
    }

    private function finish(PaymentNotifyLog $log, bool $ok, string $status, string $msg, ?RechargeOrder $order = null): array
    {
        $log->handle_status = $status;
        $log->handle_msg = mb_substr($msg, 0, 500);
        $log->handled_at = now();
        $log->save();

        return [
            'ok' => $ok,
            'message' => $msg,
            'response' => $ok ? 'success' : 'fail',
            'order' => $order,
            'code' => $ok ? 0 : ApiError::BAD_REQUEST,
            'status' => $ok ? 200 : 400,
        ];
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

    private function str(mixed $value): string
    {
        return trim((string) $value);
    }

    private function money(float|int|string|null $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function money2(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function currentSub2Balance(int $userId): ?string
    {
        try {
            $res = $this->sub2Api->user($userId);
            $value = data_get($res, 'data.balance');
        } catch (Throwable) {
            return null;
        }

        return is_numeric($value) ? $this->money($value) : null;
    }

    private function balanceFromResponse(array $res): ?string
    {
        $value = data_get($res, 'data.balance');

        return is_numeric($value) ? $this->money($value) : null;
    }
}
