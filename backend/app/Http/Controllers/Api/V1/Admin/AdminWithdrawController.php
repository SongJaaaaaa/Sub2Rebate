<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\AdminWithdrawService;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWithdrawController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(private readonly AdminWithdrawService $withdraws)
    {
    }

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $status = trim($request->string('status')->toString());

        $query = WithdrawRecord::query();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (WithdrawRecord $record): array => $this->recordPayload($record))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->operate($request, $id, 'approve');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->operate($request, $id, 'reject');
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        return $this->operate($request, $id, 'markPaid');
    }

    private function operate(Request $request, int $id, string $method): JsonResponse
    {
        $record = WithdrawRecord::query()->find($id);
        if (! $record instanceof WithdrawRecord) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '提现记录不存在', null, 404);
        }

        $result = $this->withdraws->{$method}($request->user(), $record, trim((string) $request->input('remark', '后台提现操作')));
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($this->recordPayload($result['record']));
    }

    private function recordPayload(WithdrawRecord $record): array
    {
        $user = User::query()->find((int) $record->user_id);

        return [
            'id' => (int) $record->id,
            'userId' => (int) $record->user_id,
            'username' => (string) ($user?->username ?: $user?->email ?: ''),
            'nickname' => (string) ($user?->username ?: 'user_'.$record->user_id),
            'type' => (string) ($record->type ?: WithdrawRecord::TYPE_ALIPAY),
            'amount' => $this->money($record->amount),
            'status' => (string) $record->status,
            'accountType' => (string) $record->account_type,
            'accountNo' => (string) $record->account_no,
            'realName' => (string) $record->real_name,
            'sub2ApiBalanceBefore' => $record->sub2api_balance_before !== null ? $this->money($record->sub2api_balance_before) : null,
            'sub2ApiBalanceAfter' => $record->sub2api_balance_after !== null ? $this->money($record->sub2api_balance_after) : null,
            'remark' => (string) ($record->remark ?: ''),
            'rejectReason' => (string) ($record->reject_reason ?: ''),
            'payoutTradeNo' => (string) ($record->payout_trade_no ?: ''),
            'payoutError' => (string) ($record->payout_error ?: ''),
            'payoutTime' => $record->payout_time ? $this->time($record->payout_time) : null,
            'reviewedBy' => $record->reviewed_by,
            'reviewedAt' => $record->reviewed_at ? $this->time($record->reviewed_at) : null,
            'paidAt' => $record->paid_at ? $this->time($record->paid_at) : null,
            'createdAt' => $this->time($record->created_at),
        ];
    }
}
