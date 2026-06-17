<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Auth\Services\Sub2RebateAuthService;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminBalanceController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(
        private readonly AuditLogService $audits,
        private readonly Sub2RebateAuthService $auth,
        private readonly Sub2ApiAdminClient $sub2Api,
        private readonly Sub2ApiUserRepository $sub2Users,
        private readonly RechargeEventService $recharges,
    ) {
    }

    public function apiQuota(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        try {
            $sub2User = $this->sub2Users->findById($id);
        } catch (Throwable) {
            $sub2User = null;
        }

        if ($sub2User !== null) {
            $balance = (float) $sub2User->balance;
            $charged = (float) $sub2User->totalRecharged;

            return ApiResponse::ok([
                'userId' => (int) $sub2User->id,
                'nickname' => $this->displayName($user),
                'username' => (string) ($sub2User->username ?: $user->username ?: $sub2User->email),
                'apiBalance' => $this->money($balance),
                'totalUsed' => $this->money(max(0, $charged - $balance)),
                'totalCharged' => $this->money($charged),
                'sub2ApiAffCode' => (string) ($sub2User->affCode ?? ''),
                'sub2ApiInviterId' => $sub2User->inviterId,
                'updatedAt' => $this->time($sub2User->updatedAt),
            ]);
        }

        try {
            $sub2Res = $this->sub2Api->user($id);
        } catch (Throwable $e) {
            return ApiResponse::fail(ApiError::SERVER_ERROR, $e->getMessage(), null, 502);
        }

        $data = data_get($sub2Res, 'data');
        if (! is_array($data)) {
            return ApiResponse::fail(ApiError::NOT_FOUND, 'Sub2API 用户不存在', null, 404);
        }

        $balance = (float) ($data['balance'] ?? 0);
        $charged = (float) ($data['total_recharged'] ?? 0);
        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            $username = trim((string) ($user->username ?: ($data['email'] ?? '')));
        }

        return ApiResponse::ok([
            'userId' => (int) ($data['id'] ?? $user->id),
            'nickname' => $this->displayName($user),
            'username' => $username,
            'apiBalance' => $this->money($balance),
            'totalUsed' => $this->money(max(0, $charged - $balance)),
            'totalCharged' => $this->money($charged),
            'sub2ApiAffCode' => (string) ($user->sub2api_aff_code ?: ''),
            'sub2ApiInviterId' => $user->sub2api_inviter_id,
            'updatedAt' => (string) ($data['updated_at'] ?? ''),
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $userId = (int) $request->integer('userId', $request->integer('user_id'));
        $amount = (float) $request->input('amount', 0);
        $type = trim((string) $request->input('type', 'add'));
        $reason = trim((string) $request->input('reason', '手动补偿'));
        $remark = trim((string) $request->input('remark', '返利金额调整'));

        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        if (! $this->checkAdminPassword($request)) {
            return ApiResponse::fail(ApiError::FORBIDDEN, '管理员密码错误', null, 403);
        }

        if ($amount <= 0) {
            return ApiResponse::fail(ApiError::BAD_REQUEST, '调整金额必须大于 0');
        }

        $result = DB::transaction(function () use ($request, $user, $amount, $type, $reason, $remark): array {
            RebateBalance::query()->createOrFirst(
                ['user_id' => $user->id],
                ['available_amount' => '0', 'frozen_amount' => '0', 'withdrawn_amount' => '0']
            );

            $balance = RebateBalance::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $balance->toArray();
            $delta = in_array($type, ['subtract', 'sub', 'minus'], true) ? -$amount : $amount;
            $next = (float) $balance->available_amount + $delta;
            if ($next < 0) {
                return ['ok' => false, 'message' => '可用余额不足'];
            }

            $balance->available_amount = $this->money($next, 6);
            $balance->save();

            $this->audits->record('balance', 'balance.adjust', [
                'actor' => $request->user(),
                'target' => $user,
                'subject_type' => RebateBalance::class,
                'subject_id' => $balance->id,
                'before_values' => $before,
                'after_values' => $balance->toArray() + [
                    'delta' => $this->money($delta, 6),
                    'reason' => $reason,
                ],
                'remark' => $remark !== '' ? $remark : $reason,
            ]);

            return ['ok' => true, 'balance' => $balance];
        });

        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail(ApiError::BAD_REQUEST, (string) $result['message']);
        }

        return ApiResponse::ok($this->balancePayload($result['balance']));
    }

    public function adjustApiQuota(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            return ApiResponse::fail(ApiError::BAD_REQUEST, '调整金额必须大于 0');
        }

        $type = trim((string) $request->input('type', 'add'));
        $operation = in_array($type, ['subtract', 'sub', 'minus'], true) ? 'subtract' : 'add';
        $reason = trim((string) $request->input('reason', '充值'));
        $remark = trim((string) $request->input('remark', '余额充值'));
        $notes = trim($reason.' '.$remark);
        $sourceId = 'admin-api-quota-'.$user->id.'-'.now()->format('YmdHisv');

        try {
            $sub2Res = $this->sub2Api->updateUserBalance(
                $user->id,
                $amount,
                $operation,
                $notes,
                'sub2rebate-'.$sourceId
            );
        } catch (Throwable $e) {
            return ApiResponse::fail(ApiError::SERVER_ERROR, $e->getMessage(), null, 502);
        }

        $event = null;
        if ($operation === 'add') {
            $created = $this->recharges->createRechargeEvent([
                'user_id' => $user->id,
                'source_type' => 'sub2rebate.admin_api_quota',
                'source_id' => $sourceId,
                'source_amount' => $this->money($amount, 6),
                'source_currency' => 'CNY',
                'operator_user_id' => $request->user()?->id,
                'remark' => $remark,
                'occurred_at' => now(),
            ]);

            if (! ($created['ok'] ?? false)) {
                return ApiResponse::fail(
                    (int) ($created['code'] ?? ApiError::SERVER_ERROR),
                    (string) ($created['message'] ?? '充值事件创建失败'),
                    null,
                    (int) ($created['status'] ?? 500)
                );
            }

            $event = $created['rebateEvent'] ?? null;
        }

        $this->audits->record('sub2api', 'sub2api.api_quota_adjust', [
            'actor' => $request->user(),
            'target' => $user,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'after_values' => [
                'amount' => $this->money($amount, 6),
                'operation' => $operation,
                'reason' => $reason,
                'sub2api_response' => $sub2Res,
                'rebate_event_id' => $event?->id,
            ],
            'remark' => $remark,
        ]);

        return ApiResponse::ok([
            'userId' => (int) $user->id,
            'type' => $operation === 'subtract' ? 'subtract' : 'add',
            'amount' => $this->money($amount),
            'reason' => $reason,
            'remark' => $remark,
            'sub2api' => $sub2Res,
            'rebateEventId' => $event?->id,
        ]);
    }

    public function records(Request $request, int $id): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $query = AuditLog::query()
            ->where('module', 'balance')
            ->where('target_user_id', $id);

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(function (AuditLog $log): array {
                $after = is_array($log->after_values) ? $log->after_values : [];
                $delta = (float) ($after['delta'] ?? 0);
                $type = $delta < 0 ? 'subtract' : 'add';
                $remark = (string) ($log->remark ?: '');

                return [
                    'id' => (int) $log->id,
                    'type' => $type,
                    'amount' => $this->money(abs($delta)),
                    'reason' => $remark ?: '手动补偿',
                    'remark' => $remark,
                    'operator' => $log->actor_user_id !== null ? '管理员#'.$log->actor_user_id : '系统',
                    'createdAt' => $this->time($log->created_at),
                    'tag' => $remark ?: ($type === 'add' ? '余额增加' : '余额减少'),
                    'tagColor' => $type === 'add' ? 'success' : 'danger',
                ];
            })->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    public function apiQuotaRecords(Request $request, int $id): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $query = AuditLog::query()
            ->where('module', 'sub2api')
            ->where('action', 'sub2api.api_quota_adjust')
            ->where('target_user_id', $id);

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(function (AuditLog $log): array {
                $after = is_array($log->after_values) ? $log->after_values : [];
                $type = (string) ($after['operation'] ?? 'add');

                return [
                    'id' => (int) $log->id,
                    'type' => $type === 'subtract' ? 'subtract' : 'add',
                    'amount' => $this->money($after['amount'] ?? 0),
                    'reason' => (string) ($after['reason'] ?? '充值'),
                    'remark' => (string) ($log->remark ?: ''),
                    'operator' => $log->actor_user_id !== null ? '管理员#'.$log->actor_user_id : '系统',
                    'createdAt' => $this->time($log->created_at),
                    'rebateEventId' => $after['rebate_event_id'] ?? null,
                ];
            })->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    private function balancePayload(RebateBalance $balance): array
    {
        $available = (float) $balance->available_amount;
        $frozen = (float) $balance->frozen_amount;

        return [
            'userId' => (int) $balance->user_id,
            'availableAmount' => $this->money($available),
            'frozenAmount' => $this->money($frozen),
            'totalAmount' => $this->money($available + $frozen),
            'withdrawnAmount' => $this->money($balance->withdrawn_amount),
        ];
    }

    private function checkAdminPassword(Request $request): bool
    {
        $password = (string) $request->input('adminPassword', $request->input('admin_password', ''));
        $admin = $request->user();

        if (! $admin instanceof User || $password === '') {
            return false;
        }

        $account = (string) ($admin->email ?: $admin->username);
        if ($account === '') {
            return false;
        }

        $result = $this->auth->validate($account, $password);

        return ($result['user'] ?? null) instanceof User
            && (int) $result['user']->id === (int) $admin->id
            && (string) $result['user']->role === 'admin';
    }

    private function displayName(User $user): string
    {
        $name = trim((string) ($user->username ?: ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($user->email ?: ''));
        if ($email !== '') {
            return str_contains($email, '@') ? strstr($email, '@', true) : $email;
        }

        return 'user_'.$user->id;
    }
}
