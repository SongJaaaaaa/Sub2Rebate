<?php

namespace App\Modules\Payment\Services;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\PaymentRecord;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Services\RebateEligibilityService;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

class RechargeEventService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly RebateEligibilityService $eligibility,
    )
    {
    }

    public function createManual(User $operator, User $target, array $data): array
    {
        if ($operator->role !== 'admin') {
            return [
                'ok' => false,
                'code' => ApiError::FORBIDDEN,
                'message' => '只有管理员可以补录充值事件',
                'status' => 403,
            ];
        }

        $remark = trim((string) ($data['remark'] ?? ''));
        if ($remark === '') {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '补录充值事件必须填写备注',
                'status' => 400,
            ];
        }

        return $this->createRechargeEvent([
            'user_id' => (int) $target->id,
            'source_type' => (string) ($data['source_type'] ?? 'manual_admin'),
            'source_id' => (string) ($data['source_id'] ?? ''),
            'source_amount' => (string) ($data['source_amount'] ?? ''),
            'source_currency' => (string) ($data['source_currency'] ?? 'CNY'),
            'operator_user_id' => (int) $operator->id,
            'remark' => $remark,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    public function createRechargeEvent(array $data): array
    {
        $sourceType = trim((string) ($data['source_type'] ?? ''));
        $sourceId = trim((string) ($data['source_id'] ?? ''));
        $amount = $this->amount($data['source_amount'] ?? null);

        if ($sourceType === '' || $sourceId === '') {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '充值事件来源不能为空',
                'status' => 400,
            ];
        }

        if ($amount <= 0) {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '充值金额必须大于 0',
                'status' => 400,
            ];
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $user = User::query()->find($userId);
        if ($user === null) {
            return [
                'ok' => false,
                'code' => ApiError::NOT_FOUND,
                'message' => '用户不存在',
                'status' => 404,
            ];
        }

        $existing = RebateEvent::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing !== null) {
            return [
                'ok' => true,
                'created' => false,
                'paymentRecord' => PaymentRecord::query()->find($existing->payment_record_id),
                'rebateEvent' => $existing,
            ];
        }

        $rate = $this->rate();
        $sourceCurrency = strtoupper(trim((string) ($data['source_currency'] ?? 'CNY')));
        $standardAmount = $this->money($amount);
        $creditAmount = array_key_exists('credit_amount', $data) && is_numeric($data['credit_amount'])
            ? $this->money($data['credit_amount'])
            : $this->money($amount * $rate);
        $snapshot = [
            'payment.cny_to_credit_rate' => $this->money($rate),
        ];

        return DB::transaction(function () use (
            $data,
            $user,
            $sourceType,
            $sourceId,
            $amount,
            $sourceCurrency,
            $standardAmount,
            $creditAmount,
            $snapshot
        ): array {
            $payment = PaymentRecord::query()->create([
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'paid',
                'source_amount' => $this->money($amount),
                'source_currency' => $sourceCurrency !== '' ? $sourceCurrency : 'CNY',
                'standard_amount' => $standardAmount,
                'standard_currency' => 'CNY',
                'credit_amount' => $creditAmount,
                'config_snapshot' => $snapshot,
                'operator_user_id' => $data['operator_user_id'] ?? null,
                'remark' => $data['remark'] ?? null,
                'paid_at' => $data['occurred_at'] ?? now(),
            ]);

            $event = RebateEvent::query()->create([
                'user_id' => $user->id,
                'payment_record_id' => $payment->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'event_type' => 'recharge',
                'status' => RebateEvent::STATUS_PENDING,
                'source_amount' => $this->money($amount),
                'source_currency' => $sourceCurrency !== '' ? $sourceCurrency : 'CNY',
                'standard_amount' => $standardAmount,
                'standard_currency' => 'CNY',
                'credit_amount' => $creditAmount,
                'config_snapshot' => $snapshot,
                'operator_user_id' => $data['operator_user_id'] ?? null,
                'remark' => $data['remark'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            $this->eligibility->recordSuccessfulRecharge($user->refresh(), $event->standard_amount, $event->occurred_at);

            return [
                'ok' => true,
                'created' => true,
                'paymentRecord' => $payment,
                'rebateEvent' => $event,
            ];
        });
    }

    private function amount(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return round((float) $value, 6);
    }

    private function rate(): float
    {
        $rate = $this->amount($this->configs->get('payment.cny_to_credit_rate', '1'));

        return $rate > 0 ? $rate : 1.0;
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
