<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\PaymentRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRelationshipController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(private readonly InviteService $invites)
    {
    }

    public function tree(Request $request): JsonResponse
    {
        $userId = (int) $request->integer('userId', $request->integer('user_id'));
        $maxDepth = max(1, min((int) $request->integer('maxDepth', 6), 10));

        if ($userId > 0) {
            $root = User::query()->find($userId);
            if (! $root instanceof User) {
                return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
            }

            $this->invites->ensurePath($root);

            return ApiResponse::ok([
                'root' => $this->node($root, 0, $maxDepth),
            ]);
        }

        $roots = DB::table('referral_paths')
            ->whereNull('parent_user_id')
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $users = User::query()->whereIn('id', $roots)->get()->keyBy('id');

        return ApiResponse::ok([
            'list' => collect($roots)
                ->map(fn (int $id): ?array => $users->get($id) instanceof User ? $this->node($users->get($id), 0, $maxDepth) : null)
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    private function node(User $user, int $level, int $maxDepth): array
    {
        $name = $this->displayName($user);

        $node = [
            'id' => (int) $user->id,
            'username' => $name,
            'nickname' => $name,
            'avatar' => '',
            'level' => $this->levelName($level),
            'totalRecharge' => $this->money(PaymentRecord::query()
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->sum('standard_amount')),
            'directReferrals' => DB::table('referral_paths')->where('parent_user_id', $user->id)->count(),
            'status' => $user->status === 'banned' ? 'banned' : 'active',
            'rebateStatus' => (string) ($user->rebate_status ?: 'eligible'),
            'rebateDisabledReason' => $user->rebate_disabled_reason,
            'rebateDisabledAt' => $this->time($user->rebate_disabled_at),
            'children' => [],
        ];

        if ($level >= $maxDepth) {
            return $node;
        }

        $childIds = DB::table('referral_paths')
            ->where('parent_user_id', $user->id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $children = User::query()->whereIn('id', $childIds)->get()->keyBy('id');
        foreach ($childIds as $id) {
            $child = $children->get($id);
            if ($child instanceof User) {
                $node['children'][] = $this->node($child, $level + 1, $maxDepth);
            }
        }

        return $node;
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

    private function levelName(int $level): string
    {
        return $level === 0 ? 'Top Master' : 'Referral L'.$level;
    }
}
