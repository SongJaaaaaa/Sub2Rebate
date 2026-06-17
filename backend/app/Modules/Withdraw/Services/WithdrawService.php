<?php

namespace App\Modules\Withdraw\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Risk\Services\RiskService;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Support\ApiError;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class WithdrawService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly RiskService $risks,
        private readonly AuditLogService $audits,
    ) {
    }

    public function config(): array
    {
        $min = $this->amount($this->configs->get('withdraw.min_amount', '50'));
        $dailyLimit = (int) $this->configs->get('withdraw.daily_limit', 1);
        $freezeDays = (int) $this->configs->get('withdraw.freeze_days', 0);
        $reviewMode = (string) $this->configs->get('withdraw.review_mode', 'manual');

        return [
            'minAmount' => $this->money2($min),
            'reviewMode' => $reviewMode,
            'dailyLimit' => $dailyLimit,
            'freezeDays' => $freezeDays,
            'tips' => [
                '提现最低金额为 '.$this->money2($min).' 元',
                '每日最多提现 '.$dailyLimit.' 次',
                '第一版采用人工审核和人工打款',
            ],
        ];
    }

    public function account(User $user): ?array
    {
        $account = WithdrawAccount::query()->where('user_id', $user->id)->first();

        return $account instanceof WithdrawAccount ? $this->accountPayload($account) : null;
    }

    public function saveAccount(User $user, array $data): array
    {
        $type = trim((string) ($data['type'] ?? 'alipay'));
        $realName = trim((string) ($data['realName'] ?? ''));
        $accountNo = trim((string) ($data['accountNo'] ?? ''));

        if ($realName === '' || $accountNo === '') {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '提现姓名和账号不能为空',
                'status' => 400,
            ];
        }

        $account = WithdrawAccount::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'type' => $type !== '' ? $type : 'alipay',
                'real_name' => $realName,
                'account_no' => $accountNo,
            ]
        );

        return [
            'ok' => true,
            'account' => $this->accountPayload($account),
        ];
    }

    public function apply(User $user, array $data): array
    {
        $amount = $this->amount($data['amount'] ?? null);
        $remark = trim((string) ($data['remark'] ?? ''));
        $config = $this->config();
        $min = $this->amount($config['minAmount']);

        if ($amount <= 0) {
            return $this->fail('提现金额必须大于 0');
        }

        if ($amount < $min) {
            return $this->fail('提现金额不能低于 '.$this->money2($min).' 元');
        }

        $account = WithdrawAccount::query()->where('user_id', $user->id)->first();
        if (! $account instanceof WithdrawAccount) {
            return $this->fail('请先绑定提现账号');
        }

        if ($this->todayCount($user) >= (int) $config['dailyLimit']) {
            return $this->fail('今日提现次数已达上限');
        }

        $risk = $this->risks->canWithdraw($user);
        if (! ($risk['ok'] ?? false)) {
            return $this->fail((string) $risk['message']);
        }

        return DB::transaction(function () use ($user, $account, $amount, $remark): array {
            RebateBalance::query()->createOrFirst(
                ['user_id' => $user->id],
                [
                    'available_amount' => '0',
                    'frozen_amount' => '0',
                    'withdrawn_amount' => '0',
                ]
            );

            $balance = RebateBalance::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $balance->available_amount < $amount) {
                return $this->fail('可提现余额不足');
            }

            $balance->available_amount = $this->money((float) $balance->available_amount - $amount);
            $balance->frozen_amount = $this->money((float) $balance->frozen_amount + $amount);
            $balance->save();

            $record = WithdrawRecord::query()->create([
                'user_id' => $user->id,
                'withdraw_account_id' => $account->id,
                'amount' => $this->money($amount),
                'status' => WithdrawRecord::STATUS_PENDING,
                'account_type' => $account->type,
                'account_no' => $account->account_no,
                'real_name' => $account->real_name,
                'remark' => $remark,
            ]);

            $this->audits->record('withdraw', 'withdraw.apply', [
                'actor' => $user,
                'target' => $user,
                'subject_type' => WithdrawRecord::class,
                'subject_id' => $record->id,
                'after_values' => [
                    'amount' => $this->money($amount),
                    'status' => WithdrawRecord::STATUS_PENDING,
                    'available_amount' => $balance->available_amount,
                    'frozen_amount' => $balance->frozen_amount,
                ],
                'remark' => $remark,
            ]);

            return [
                'ok' => true,
                'record' => $this->recordPayload($record),
                'balance' => $this->balancePayload($balance),
            ];
        });
    }

    public function records(User $user, int $page = 1, int $pageSize = 20, string $status = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 100));

        $query = WithdrawRecord::query()->where('user_id', $user->id);
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get();

        return [
            'list' => $rows->map(fn (WithdrawRecord $record): array => $this->recordPayload($record))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    private function todayCount(User $user): int
    {
        return WithdrawRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    private function accountPayload(WithdrawAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'type' => $account->type,
            'realName' => $account->real_name,
            'accountNo' => $account->account_no,
            'createdAt' => $this->time($account->created_at),
            'updatedAt' => $this->time($account->updated_at),
        ];
    }

    private function recordPayload(WithdrawRecord $record): array
    {
        return [
            'id' => (int) $record->id,
            'amount' => $this->money2($record->amount),
            'status' => $record->status,
            'accountType' => $record->account_type,
            'accountNo' => $record->account_no,
            'realName' => $record->real_name,
            'remark' => $record->remark ?? '',
            'rejectReason' => $record->reject_reason ?? '',
            'paidAt' => $record->paid_at ? $this->time($record->paid_at) : null,
            'createdAt' => $this->time($record->created_at),
        ];
    }

    private function balancePayload(RebateBalance $balance): array
    {
        $available = (float) $balance->available_amount;
        $frozen = (float) $balance->frozen_amount;
        $withdrawn = (float) $balance->withdrawn_amount;

        return [
            'availableAmount' => $this->money2($available),
            'frozenAmount' => $this->money2($frozen),
            'totalAmount' => $this->money2($available + $frozen),
            'withdrawnAmount' => $this->money2($withdrawn),
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

    private function amount(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return round((float) $value, 6);
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
}
