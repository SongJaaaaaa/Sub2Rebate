<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminRechargeOrderService
{
    public function __construct(
        private readonly AdminAccessService $access,
        private readonly AuditLogService $audits,
        private readonly Sub2ApiAdminClient $sub2Api,
        private readonly RechargeEventService $recharges,
    ) {
    }

    public function approve(User $admin, RechargeOrder $order, string $remark): array
    {
        $check = $this->check($admin, $remark);
        if (! ($check['ok'] ?? false)) {
            return $check;
        }

        if ($order->status !== RechargeOrder::STATUS_SUBMITTED) {
            return $this->fail('只有待审核到账的订单可以通过');
        }

        $sourceId = 'recharge-order-'.$order->order_no;

        try {
            $sub2Res = $this->sub2Api->updateUserBalance(
                $order->user_id,
                (float) $order->credit_amount,
                'add',
                '二维码充值 '.$order->order_no,
                'sub2rebate-'.$sourceId
            );
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'code' => ApiError::SERVER_ERROR,
                'message' => $e->getMessage(),
                'status' => 502,
            ];
        }

        return DB::transaction(function () use ($admin, $order, $remark, $sourceId, $sub2Res): array {
            $before = $order->toArray();

            $created = $this->recharges->createRechargeEvent([
                'user_id' => $order->user_id,
                'source_type' => 'sub2rebate.recharge_order',
                'source_id' => $sourceId,
                'source_amount' => $this->money($order->amount),
                'source_currency' => 'CNY',
                'credit_amount' => $this->money($order->credit_amount),
                'operator_user_id' => $admin->id,
                'remark' => '二维码充值到账',
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

            $order->status = RechargeOrder::STATUS_APPROVED;
            $order->reviewed_by = $admin->id;
            $order->reviewed_at = now();
            $order->paid_at = now();
            $order->review_remark = $remark;
            $order->rebate_event_id = $created['rebateEvent']->id ?? null;
            $order->save();

            $this->audits->record('payment', 'payment.recharge_order_approve', [
                'actor' => $admin,
                'target_user_id' => $order->user_id,
                'subject_type' => RechargeOrder::class,
                'subject_id' => $order->id,
                'before_values' => $before,
                'after_values' => $order->toArray() + ['sub2api_response' => $sub2Res],
                'remark' => $remark,
            ]);

            return [
                'ok' => true,
                'order' => $order,
            ];
        });
    }

    public function reject(User $admin, RechargeOrder $order, string $remark): array
    {
        $check = $this->check($admin, $remark);
        if (! ($check['ok'] ?? false)) {
            return $check;
        }

        if (! in_array($order->status, [RechargeOrder::STATUS_PENDING, RechargeOrder::STATUS_SUBMITTED], true)) {
            return $this->fail('当前订单状态不能拒绝');
        }

        $before = $order->toArray();
        $order->status = RechargeOrder::STATUS_REJECTED;
        $order->reviewed_by = $admin->id;
        $order->reviewed_at = now();
        $order->review_remark = $remark;
        $order->save();

        $this->audits->record('payment', 'payment.recharge_order_reject', [
            'actor' => $admin,
            'target_user_id' => $order->user_id,
            'subject_type' => RechargeOrder::class,
            'subject_id' => $order->id,
            'before_values' => $before,
            'after_values' => $order->toArray(),
            'remark' => $remark,
        ]);

        return [
            'ok' => true,
            'order' => $order,
        ];
    }

    private function check(User $admin, string $remark): array
    {
        if (! $this->access->isAdmin($admin)) {
            return [
                'ok' => false,
                'code' => ApiError::FORBIDDEN,
                'message' => '只有管理员可以操作',
                'status' => 403,
            ];
        }

        if (trim($remark) === '') {
            return $this->fail('后台敏感操作必须填写备注');
        }

        return ['ok' => true];
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

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}