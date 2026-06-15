<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use FormatsApiPayloads;

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $balance = RebateBalance::query()->where('user_id', $user->id)->first();
        $available = (float) ($balance?->available_amount ?? 0);
        $frozen = (float) ($balance?->frozen_amount ?? 0);

        $directInviteCount = \Illuminate\Support\Facades\DB::table('referral_paths')
            ->where('parent_user_id', $user->id)
            ->count();
        $teamInviteCount = 0;
        $ref = \Illuminate\Support\Facades\DB::table('referral_paths')->where('user_id', $user->id)->first();
        if ($ref) {
            $prefix = trim((string) $ref->path, '/').'/';
            $teamInviteCount = \Illuminate\Support\Facades\DB::table('referral_paths')
                ->where('path', 'like', $prefix.'%')
                ->count();
        }

        return ApiResponse::ok([
            'availableAmount' => $this->money($available),
            'frozenAmount' => $this->money($frozen),
            'totalAmount' => $this->money($available + $frozen),
            'withdrawnAmount' => $this->money($balance?->withdrawn_amount ?? 0),
            'totalRebateAmount' => $this->money(RebateRecord::query()->where('receiver_user_id', $user->id)->sum('rebate_amount')),
            'todayRebateAmount' => $this->money(RebateRecord::query()->where('receiver_user_id', $user->id)->where('created_at', '>=', $today)->sum('rebate_amount')),
            'monthRebateAmount' => $this->money(RebateRecord::query()->where('receiver_user_id', $user->id)->where('created_at', '>=', $monthStart)->sum('rebate_amount')),
            'directInviteCount' => $directInviteCount,
            'teamInviteCount' => $teamInviteCount,
            'pendingWithdrawCount' => WithdrawRecord::query()
                ->where('user_id', $user->id)
                ->where('status', WithdrawRecord::STATUS_PENDING)
                ->count(),
            'pendingWithdrawAmount' => $this->money(WithdrawRecord::query()
                ->where('user_id', $user->id)
                ->where('status', WithdrawRecord::STATUS_PENDING)
                ->sum('amount')),
        ]);
    }

    public function rebateTrends(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $days = $request->string('range')->toString() === '30d' ? 30 : 7;
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = RebateRecord::query()
            ->selectRaw('date(created_at) as day, coalesce(sum(rebate_amount), 0) as amount')
            ->where('receiver_user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('amount', 'day');

        $list = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $list[] = [
                'date' => $day,
                'rebateAmount' => $this->money($rows[$day] ?? 0),
            ];
        }

        return ApiResponse::ok([
            'items' => $list,
        ]);
    }

    public function recentActivities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $rows = AuditLog::query()
            ->where(function ($query) use ($user): void {
                $query->where('actor_user_id', $user->id)
                    ->orWhere('target_user_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (AuditLog $log): array => [
                'id' => (int) $log->id,
                'type' => (string) $log->module,
                'title' => (string) $log->action,
                'content' => (string) ($log->remark ?: ''),
                'amount' => '',
                'createdAt' => $this->time($log->created_at),
            ])->all(),
        ]);
    }
}
