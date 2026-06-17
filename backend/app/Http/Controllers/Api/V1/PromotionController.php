<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\PaymentRecord;
use App\Modules\Rebate\Models\RebateRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(private readonly InviteService $invites)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $invite = $this->invites->me($user);
        $ref = $this->invites->refreshSub2ApiTeam($user);
        $prefix = trim((string) $ref->path, '/').'/';
        $teamIds = DB::table('referral_paths')
            ->where('path', 'like', $prefix.'%')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $conversionCount = $teamIds === []
            ? 0
            : PaymentRecord::query()->whereIn('user_id', $teamIds)->distinct('user_id')->count('user_id');
        $conversionRate = count($teamIds) > 0 ? $conversionCount / count($teamIds) : 0;

        return ApiResponse::ok([
            'inviteCode' => $invite['inviteCode'],
            'inviteUrl' => $invite['inviteUrl'],
            'sub2ApiAffCode' => $invite['sub2ApiAffCode'],
            'sub2ApiInviteUrl' => $invite['sub2ApiInviteUrl'],
            'sub2ApiAffiliatePageUrl' => $invite['sub2ApiAffiliatePageUrl'],
            'directInviteCount' => DB::table('referral_paths')->where('parent_user_id', $user->id)->count(),
            'teamInviteCount' => count($teamIds),
            'conversionCount' => $conversionCount,
            'totalPaidUserCount' => $conversionCount,
            'conversionRate' => number_format($conversionRate, 6, '.', ''),
            'totalRebateAmount' => $this->money(RebateRecord::query()->where('receiver_user_id', $user->id)->sum('rebate_amount')),
        ]);
    }

    public function conversions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $ref = $this->invites->refreshSub2ApiTeam($user);
        $prefix = trim((string) $ref->path, '/').'/';

        $query = DB::table('referral_paths as rp')
            ->join('users as u', 'u.id', '=', 'rp.user_id')
            ->leftJoin('payment_records as p', 'p.user_id', '=', 'u.id')
            ->where('rp.path', 'like', $prefix.'%')
            ->groupBy('u.id', 'u.username', 'u.email', 'rp.depth', 'rp.updated_at')
            ->selectRaw('u.id, u.username, u.email, rp.depth, rp.updated_at, coalesce(sum(p.standard_amount), 0) as total_amount, count(p.id) as pay_count');

        $total = DB::query()->fromSub($query, 't')->count();
        $rows = $query->orderBy('rp.depth')->orderBy('u.id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn ($row): array => [
                'id' => (int) $row->id,
                'username' => (string) ($row->username ?: $row->email ?: 'user_'.$row->id),
                'level' => max(1, (int) $row->depth - (int) $ref->depth),
                'payCount' => (int) $row->pay_count,
                'totalPaidAmount' => $this->money($row->total_amount),
                'boundAt' => (string) $row->updated_at,
            ])->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    public function rebateRecords(Request $request): JsonResponse
    {
        return app(RebateRecordController::class)->index($request);
    }
}
