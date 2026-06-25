<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Epay 已支付订单结算：把一笔已收到 Epay 成功通知的订单入账。
 *
 * 复刻人工 approve 的入账动作（Sub2API 加余额 + createRechargeEvent 建返利事件 + 更新订单），
 * 并**复用与人工 approve 完全相同的幂等键** source_id='recharge-order-{order_no}'，
 * 因此自动回调与人工补单针对同一订单不会重复入账。
 */
class EpaySettlementService
{
    public function __construct(
        private readonly Sub2ApiAdminClient $sub2Api,
        private readonly RechargeEventService $recharges,
    ) {
    }

    /**
     * @param  array<string,mixed>  $rawNotify  Epay 原始回调参数（脱敏后留痕）
     * @return array{ok:bool, code?:int, message?:string, status?:int}
     */
    public function settle(RechargeOrder $order, string $epayTradeNo, string $paidAmount, string $payMethod, array $rawNotify): array
    {
        $sourceId = 'recharge-order-' . $order->order_no;

        // 1. Sub2API 加余额（按 idempotencyKey 幂等，与 approve 同键）
        try {
            $balanceRes = $this->sub2Api->updateUserBalance(
                $order->user_id,
                (float) $order->credit_amount,
                'add',
                'Epay当面付充值 ' . $order->order_no,
                'sub2rebate-' . $sourceId,
            );
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'code' => ApiError::SERVER_ERROR,
                'message' => $e->getMessage(),
                'status' => 502,
            ];
        }

        // 加款后的新余额（Sub2API 返回），用于付款日志
        $afterBalance = data_get($balanceRes, 'data.balance');
        $afterBalance = $afterBalance === null ? null : number_format((float) $afterBalance, 6, '.', '');

        // 2. 事务：建返利事件 + 更新订单
        return DB::transaction(function () use ($order, $sourceId, $epayTradeNo, $paidAmount, $payMethod, $rawNotify, $afterBalance): array {
            $created = $this->recharges->createRechargeEvent([
                'user_id' => $order->user_id,
                'source_type' => 'sub2rebate.recharge_order',
                'source_id' => $sourceId,
                'source_amount' => number_format((float) $order->amount, 6, '.', ''),
                'source_currency' => 'CNY',
                'credit_amount' => number_format((float) $order->credit_amount, 6, '.', ''),
                'remark' => 'Epay当面付到账 trade_no=' . $epayTradeNo,
                'occurred_at' => now(),
            ]);

            if (! ($created['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'code' => (int) ($created['code'] ?? ApiError::SERVER_ERROR),
                    'message' => (string) ($created['message'] ?? '创建充值事件失败'),
                    'status' => (int) ($created['status'] ?? 500),
                ];
            }

            $order->status = RechargeOrder::STATUS_PAID;
            $order->pay_method = $payMethod;
            $order->epay_trade_no = $epayTradeNo;
            $order->epay_paid_amount = $paidAmount;
            $order->sub2_balance_after = $afterBalance;
            $order->notify_raw = $rawNotify;
            $order->paid_at = now();
            $order->rebate_event_id = $created['rebateEvent']->id ?? null;
            $order->save();

            return ['ok' => true];
        });
    }
}
