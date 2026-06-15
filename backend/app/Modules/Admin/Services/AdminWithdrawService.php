<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

class AdminWithdrawService
{
    public function __construct(
        private readonly AdminAccessService $access,
        private readonly AuditLogService $audits,
    ) {
    }

    public function approve(User $admin, WithdrawRecord $record, string $remark): array
    {
        $check = $this->check($admin, $remark);
        if (! ($check['ok'] ?? false)) {
            return $check;
        }

        if ($record->status !== WithdrawRecord::STATUS_PENDING) {
            return $this->fail('只有待审核提现可以通过');
        }

        $before = $record->toArray();
        $record->status = WithdrawRecord::STATUS_APPROVED;
        $record->reviewed_by = $admin->id;
        $record->reviewed_at = now();
        $record->save();

        $this->audit($admin, $record, 'withdraw.approve', $before, $remark);

        return [
            'ok' => true,
            'record' => $record,
        ];
    }

    public function reject(User $admin, WithdrawRecord $record, string $remark): array
    {
        $check = $this->check($admin, $remark);
        if (! ($check['ok'] ?? false)) {
            return $check;
        }

        if (! in_array($record->status, [WithdrawRecord::STATUS_PENDING, WithdrawRecord::STATUS_APPROVED], true)) {
            return $this->fail('当前提现状态不能拒绝');
        }

        return DB::transaction(function () use ($admin, $record, $remark): array {
            $before = $record->toArray();
            $balance = RebateBalance::query()
                ->where('user_id', $record->user_id)
                ->lockForUpdate()
                ->first();
            if (! $balance instanceof RebateBalance) {
                return $this->fail('返利余额不存在');
            }

            $amount = (float) $record->amount;
            $balance->frozen_amount = $this->money(max(0, (float) $balance->frozen_amount - $amount));
            $balance->available_amount = $this->money((float) $balance->available_amount + $amount);
            $balance->save();

            $record->status = WithdrawRecord::STATUS_REJECTED;
            $record->reject_reason = $remark;
            $record->reviewed_by = $admin->id;
            $record->reviewed_at = now();
            $record->save();

            $this->audit($admin, $record, 'withdraw.reject', $before, $remark);

            return [
                'ok' => true,
                'record' => $record,
            ];
        });
    }

    public function markPaid(User $admin, WithdrawRecord $record, string $remark): array
    {
        $check = $this->check($admin, $remark);
        if (! ($check['ok'] ?? false)) {
            return $check;
        }

        if ($record->status !== WithdrawRecord::STATUS_APPROVED) {
            return $this->fail('只有已审核通过的提现可以标记打款');
        }

        return DB::transaction(function () use ($admin, $record, $remark): array {
            $before = $record->toArray();
            $balance = RebateBalance::query()
                ->where('user_id', $record->user_id)
                ->lockForUpdate()
                ->first();
            if (! $balance instanceof RebateBalance) {
                return $this->fail('返利余额不存在');
            }

            $amount = (float) $record->amount;
            if ((float) $balance->frozen_amount < $amount) {
                return $this->fail('冻结余额不足');
            }

            $balance->frozen_amount = $this->money((float) $balance->frozen_amount - $amount);
            $balance->withdrawn_amount = $this->money((float) $balance->withdrawn_amount + $amount);
            $balance->save();

            $record->status = WithdrawRecord::STATUS_PAID;
            $record->reviewed_by = $admin->id;
            $record->reviewed_at = $record->reviewed_at ?? now();
            $record->paid_at = now();
            $record->save();

            $this->audit($admin, $record, 'withdraw.mark_paid', $before, $remark);

            return [
                'ok' => true,
                'record' => $record,
            ];
        });
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

        return [
            'ok' => true,
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

    private function audit(User $admin, WithdrawRecord $record, string $action, array $before, string $remark): void
    {
        $this->audits->record('withdraw', $action, [
            'actor' => $admin,
            'target_user_id' => $record->user_id,
            'subject_type' => WithdrawRecord::class,
            'subject_id' => $record->id,
            'before_values' => $before,
            'after_values' => $record->toArray(),
            'remark' => $remark,
        ]);
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
