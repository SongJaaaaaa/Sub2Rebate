<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    use FormatsApiPayloads;

    public function dashboard(): JsonResponse
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $balance = RebateBalance::query()
            ->selectRaw('coalesce(sum(available_amount), 0) as available, coalesce(sum(frozen_amount), 0) as frozen')
            ->first();

        return ApiResponse::ok([
            'totalUsers' => User::query()->count(),
            'todayNewUsers' => User::query()->where('created_at', '>=', $today)->count(),
            'totalRebateAmount' => $this->money(RebateRecord::query()->sum('rebate_amount')),
            'todayRebateAmount' => $this->money(RebateRecord::query()->where('created_at', '>=', $today)->sum('rebate_amount')),
            'monthRebateAmount' => $this->money(RebateRecord::query()->where('created_at', '>=', $monthStart)->sum('rebate_amount')),
            'totalWithdrawAmount' => $this->money(WithdrawRecord::query()->where('status', WithdrawRecord::STATUS_PAID)->sum('amount')),
            'pendingWithdrawCount' => WithdrawRecord::query()->where('status', WithdrawRecord::STATUS_PENDING)->count(),
            'pendingWithdrawAmount' => $this->money(WithdrawRecord::query()->where('status', WithdrawRecord::STATUS_PENDING)->sum('amount')),
            'rebateBalanceAmount' => $this->money((float) $balance->available + (float) $balance->frozen),
            'pendingEventCount' => RebateEvent::query()->where('status', RebateEvent::STATUS_PENDING)->count(),
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $days = $request->string('range')->toString() === '30d' ? 30 : 7;
        $start = now()->subDays($days - 1)->startOfDay();

        $rebateRows = RebateRecord::query()
            ->selectRaw('date(created_at) as day, coalesce(sum(rebate_amount), 0) as amount')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('amount', 'day');

        $withdrawRows = WithdrawRecord::query()
            ->where('status', WithdrawRecord::STATUS_PAID)
            ->selectRaw('date(created_at) as day, coalesce(sum(amount), 0) as amount')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('amount', 'day');

        $userRows = User::query()
            ->selectRaw('date(created_at) as day, count(*) as cnt')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $list = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $list[] = [
                'date' => $day,
                'rebateAmount' => $this->money($rebateRows[$day] ?? 0),
                'withdrawAmount' => $this->money($withdrawRows[$day] ?? 0),
                'newUsers' => (int) ($userRows[$day] ?? 0),
            ];
        }

        return ApiResponse::ok([
            'range' => $days === 30 ? '30d' : '7d',
            'items' => $list,
        ]);
    }
}
