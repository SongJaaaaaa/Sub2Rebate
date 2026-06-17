<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Payment\Models\PaymentRecord;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\RebateRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(private readonly AuditLogService $audits)
    {
    }

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $keyword = trim($request->string('keyword')->toString());

        $query = User::query();
        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->where(function ($q) use ($keyword, $like): void {
                $q->where('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('id', $keyword);
            });
        }

        $total = (clone $query)->count();
        $users = $query->orderByDesc('created_at')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $users->map(fn (User $user): array => $this->userPayload($user))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    public function ban(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($request, $id, 'banned', 'user.ban');
    }

    public function unban(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($request, $id, 'active', 'user.unban');
    }

    public function setRole(Request $request, int $id): JsonResponse
    {
        $role = trim((string) $request->input('role', 'user'));
        if (! in_array($role, ['user', 'admin'], true)) {
            return ApiResponse::fail(ApiError::BAD_REQUEST, '角色不正确');
        }

        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        $before = $user->toArray();
        $user->role = $role;
        $user->save();
        $this->audit($request, $user, 'user.set_role', $before);

        return ApiResponse::ok($this->userPayload($user));
    }

    private function setStatus(Request $request, int $id, string $status, string $action): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        $before = $user->toArray();
        $user->status = $status;
        $user->save();
        if ($status !== 'active') {
            $user->tokens()->delete();
        }
        $this->audit($request, $user, $action, $before);

        return ApiResponse::ok($this->userPayload($user));
    }

    private function audit(Request $request, User $user, string $action, array $before): void
    {
        $this->audits->record('user', $action, [
            'actor' => $request->user(),
            'target' => $user,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'before_values' => $before,
            'after_values' => $user->toArray(),
            'remark' => trim((string) $request->input('remark', '后台用户操作')),
        ]);
    }

    private function userPayload(User $user): array
    {
        $name = $this->displayName($user);
        $parent = $user->sub2api_inviter_id !== null
            ? User::query()->find((int) $user->sub2api_inviter_id)
            : null;
        $balance = RebateBalance::query()->where('user_id', $user->id)->first();
        $balanceTotal = $balance instanceof RebateBalance
            ? (float) $balance->available_amount + (float) $balance->frozen_amount + (float) $balance->withdrawn_amount
            : (float) RebateRecord::query()->where('receiver_user_id', $user->id)->sum('rebate_amount');

        return [
            'id' => (int) $user->id,
            'username' => (string) ($user->username ?: $name),
            'nickname' => $name,
            'email' => (string) ($user->email ?: ''),
            'avatar' => '',
            'role' => (string) $user->role,
            'status' => (string) $user->status,
            'parentNickname' => $parent instanceof User ? $this->displayName($parent) : null,
            'directInviteCount' => DB::table('referral_paths')->where('parent_user_id', $user->id)->count(),
            'totalRebateAmount' => $this->money($balanceTotal),
            'totalPaidAmount' => $this->money(PaymentRecord::query()->where('user_id', $user->id)->sum('standard_amount')),
            'sub2ApiAffCode' => (string) ($user->sub2api_aff_code ?: ''),
            'sub2ApiInviterId' => $user->sub2api_inviter_id,
            'createdAt' => $this->time($user->created_at),
        ];
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
