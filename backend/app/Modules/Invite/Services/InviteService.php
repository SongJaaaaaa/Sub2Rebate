<?php

namespace App\Modules\Invite\Services;

use App\Models\User;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use stdClass;

class InviteService
{
    private const SUB2API_MISSING = 'missing';
    private const SUB2API_ERROR = 'error';

    public function __construct(
        private readonly Sub2ApiUserRepository $sub2Users,
        private readonly Sub2ApiAdminClient $sub2Api,
    ) {
    }

    public function me(User $user): array
    {
        $ref = $this->refreshSub2ApiTeam($user);
        $sub2User = $this->sub2UserFor($user);
        $affCode = (string) ($sub2User?->affCode ?: $user->sub2api_aff_code ?: $this->sub2ApiAffCode($user));

        return [
            'inviteCode' => '',
            'inviteUrl' => '',
            'sub2ApiAffCode' => $affCode,
            'sub2ApiInviteUrl' => $this->makeUrl(
                (string) config('sub2rebate.sub2api_invite_url_template'),
                $affCode
            ),
            'sub2ApiAffiliatePageUrl' => (string) config('sub2rebate.sub2api_affiliate_page_url'),
            'parent' => $this->userBrief($ref->parent_user_id !== null ? User::query()->find((int) $ref->parent_user_id) : null),
            'depth' => (int) $ref->depth,
            'directInviteCount' => $this->directCount((int) $user->id),
            'teamInviteCount' => $this->teamCount((int) $user->id),
        ];
    }

    public function bind(User $user, string $inviteCode): array
    {
        $inviteCode = Str::upper(trim($inviteCode));
        if ($inviteCode === '') {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '邀请码不能为空',
                'status' => 400,
            ];
        }

        $self = $this->ensurePath($user);
        if ($self->parent_user_id !== null) {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '已绑定邀请关系',
                'status' => 422,
            ];
        }

        $parent = DB::table('referral_paths')
            ->where('invite_code', $inviteCode)
            ->first();

        if ($parent === null) {
            return [
                'ok' => false,
                'code' => ApiError::NOT_FOUND,
                'message' => '邀请码不存在',
                'status' => 404,
            ];
        }

        if ((int) $parent->user_id === (int) $user->id) {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '不能绑定自己的邀请码',
                'status' => 422,
            ];
        }

        if ($this->pathHasUser((string) $parent->path, (int) $user->id)) {
            return [
                'ok' => false,
                'code' => ApiError::BAD_REQUEST,
                'message' => '不能形成循环邀请关系',
                'status' => 422,
            ];
        }

        DB::transaction(function () use ($user, $parent): void {
            $newPath = trim((string) $parent->path, '/').'/'.$user->id;
            $newDepth = (int) $parent->depth + 1;

            DB::table('referral_paths')
                ->where('user_id', $user->id)
                ->update([
                    'parent_user_id' => (int) $parent->user_id,
                    'path' => $newPath,
                    'depth' => $newDepth,
                    'updated_at' => now(),
                ]);

            User::query()
                ->where('id', (int) $parent->user_id)
                ->update(['last_invited_at' => now(), 'updated_at' => now()]);

            $this->refreshChildren((int) $user->id, $newPath, $newDepth);
        });

        $parentUser = User::query()->find((int) $parent->user_id);

        return [
            'ok' => true,
            'data' => [
                'bound' => true,
                'parent' => $this->userBrief($parentUser),
            ],
        ];
    }

    public function tree(User $user, int $maxDepth = 3): array
    {
        $maxDepth = max(1, min($maxDepth, 10));
        $this->refreshSub2ApiTeam($user);

        return [
            'root' => $this->treeNode($user, 0, $maxDepth),
        ];
    }

    public function records(User $user, int $page = 1, int $pageSize = 20): array
    {
        $root = $this->refreshSub2ApiTeam($user);
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 100));
        $prefix = trim((string) $root->path, '/').'/';

        $query = DB::table('referral_paths as rp')
            ->join('users as u', 'u.id', '=', 'rp.user_id')
            ->where('rp.path', 'like', $prefix.'%')
            ->orderBy('rp.depth')
            ->orderBy('rp.created_at');

        $total = (clone $query)->count();
        $rows = $query
            ->forPage($page, $pageSize)
            ->get();

        return [
            'list' => $rows->map(function (stdClass $row) use ($root): array {
                $level = max(1, (int) $row->depth - (int) $root->depth);

                return [
                    'id' => (int) $row->user_id,
                    'username' => (string) ($row->username ?: $row->email ?: 'user_'.$row->user_id),
                    'nickname' => (string) ($row->username ?: $row->email ?: 'user_'.$row->user_id),
                    'level' => $level,
                    'totalPaidAmount' => $this->money(0),
                    'totalRebateAmount' => $this->money(0),
                    'rebateStatus' => (string) ($row->rebate_status ?? 'eligible'),
                    'rebateDisabledReason' => $row->rebate_disabled_reason ?? null,
                    'boundAt' => $this->formatTime($row->updated_at),
                ];
            })->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ];
    }

    public function ensurePath(User $user): stdClass
    {
        $row = DB::table('referral_paths')
            ->where('user_id', $user->id)
            ->first();

        if ($row !== null) {
            return $row;
        }

        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(6));

            try {
                DB::table('referral_paths')->insert([
                    'user_id' => $user->id,
                    'parent_user_id' => null,
                    'invite_code' => $code,
                    'path' => (string) $user->id,
                    'depth' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                $row = DB::table('referral_paths')
                    ->where('user_id', $user->id)
                    ->first();
                if ($row !== null) {
                    return $row;
                }

                continue;
            }

            return DB::table('referral_paths')->where('user_id', $user->id)->first();
        }

        throw new RuntimeException('generate invite code failed');
    }

    public function syncFromSub2Api(User $user, array $seen = []): stdClass
    {
        if (count($seen) >= 50) {
            return $this->ensurePath($user);
        }

        if (in_array((int) $user->id, $seen, true)) {
            return $this->ensurePath($user);
        }
        $seen[] = (int) $user->id;

        $sub2User = $this->sub2UserFor($user);
        if ($sub2User !== null) {
            $user->forceFill([
                'username' => $sub2User->username,
                'email' => $sub2User->email,
                'status' => $sub2User->status,
                'sub2api_aff_code' => $sub2User->affCode,
                'sub2api_inviter_id' => $sub2User->inviterId,
            ])->save();
        }

        $ref = $this->ensurePath($user);
        $ref = $this->syncInviteCode($user, $ref, $sub2User?->affCode);
        $parentId = $sub2User?->inviterId ?? $user->sub2api_inviter_id;
        if ($parentId === null || (int) $parentId === (int) $user->id) {
            return $ref;
        }

        if ($ref->parent_user_id !== null) {
            return $ref;
        }

        $parent = User::query()->find((int) $parentId);
        if (! $parent instanceof User) {
            $sub2Parent = $this->sub2User((int) $parentId);
            if ($sub2Parent === null) {
                return $ref;
            }

            $parent = User::query()->updateOrCreate(
                ['id' => $sub2Parent->id],
                [
                    'username' => $sub2Parent->username,
                    'email' => $sub2Parent->email,
                    'role' => in_array($sub2Parent->role, ['user', 'admin'], true) ? $sub2Parent->role : 'user',
                    'status' => $sub2Parent->status,
                    'sub2api_aff_code' => $sub2Parent->affCode,
                    'sub2api_inviter_id' => $sub2Parent->inviterId,
                ]
            );
        }

        $parentRef = $this->syncFromSub2Api($parent, $seen);
        if ($this->pathHasUser((string) $parentRef->path, (int) $user->id)) {
            return $ref;
        }

        $newPath = trim((string) $parentRef->path, '/').'/'.$user->id;
        $newDepth = (int) $parentRef->depth + 1;

        DB::table('referral_paths')
            ->where('user_id', $user->id)
            ->update([
                'parent_user_id' => (int) $parent->id,
                'invite_code' => (string) ($sub2User?->affCode ?? $user->sub2api_aff_code ?? $ref->invite_code),
                'path' => $newPath,
                'depth' => $newDepth,
                'updated_at' => now(),
            ]);

        $parent->forceFill([
            'last_invited_at' => now(),
        ])->save();

        $this->refreshChildren((int) $user->id, $newPath, $newDepth);

        return DB::table('referral_paths')->where('user_id', $user->id)->first();
    }

    private function syncInviteCode(User $user, stdClass $ref, ?string $affCode = null): stdClass
    {
        $code = trim((string) ($affCode ?: $user->sub2api_aff_code ?: ''));
        if ($code === '' || (string) $ref->invite_code === $code) {
            return $ref;
        }

        DB::table('referral_paths')
            ->where('user_id', $user->id)
            ->update([
                'invite_code' => $code,
                'updated_at' => now(),
            ]);

        return DB::table('referral_paths')->where('user_id', $user->id)->first() ?? $ref;
    }

    public function refreshSub2ApiTeam(User $user): stdClass
    {
        if ($this->sub2UserFor($user) === null) {
            return $this->syncFromSub2Api($user);
        }

        $root = $this->syncFromSub2Api($user);
        $this->syncSub2ApiChildren($user, [(int) $user->id]);
        $prefix = trim((string) $root->path, '/').'/';

        $rows = DB::table('referral_paths')
            ->where('path', 'like', $prefix.'%')
            ->orderByDesc('depth')
            ->get(['user_id']);

        foreach ($rows as $row) {
            $id = (int) $row->user_id;
            $status = $this->sub2UserStatus($id);
            if ($status instanceof Sub2ApiUserData) {
                $child = User::query()->find($id);
                if ($child instanceof User) {
                    $this->syncFromSub2Api($child);
                }
                continue;
            }

            if ($status === self::SUB2API_MISSING) {
                $this->detachReferralBranch($id);
            }
        }

        return DB::table('referral_paths')->where('user_id', $user->id)->first() ?? $root;
    }

    /**
     * @param array<int> $seen
     */
    private function syncSub2ApiChildren(User $user, array $seen): void
    {
        if (count($seen) >= 50) {
            return;
        }

        try {
            $children = $this->sub2Users->childrenOf((int) $user->id);
        } catch (Throwable) {
            return;
        }

        foreach ($children as $childData) {
            if (in_array($childData->id, $seen, true)) {
                continue;
            }

            $child = User::query()->updateOrCreate(
                ['id' => $childData->id],
                [
                    'username' => $childData->username,
                    'email' => $childData->email,
                    'role' => in_array($childData->role, ['user', 'admin'], true) ? $childData->role : 'user',
                    'status' => $childData->status,
                    'sub2api_aff_code' => $childData->affCode,
                    'sub2api_inviter_id' => $childData->inviterId,
                ]
            );

            $this->syncFromSub2Api($child, $seen);
            $this->syncSub2ApiChildren($child, [...$seen, $childData->id]);
        }
    }

    public function ancestorIds(User $user): array
    {
        $ref = $this->ensurePath($user);
        $ids = array_map('intval', array_filter(explode('/', trim((string) $ref->path, '/'))));
        array_pop($ids);

        return array_reverse($ids);
    }

    public function rebateAncestors(User $user): array
    {
        $ids = $this->ancestorIds($user);
        if ($ids === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $index => $id) {
            $ancestor = $users->get($id);
            if (! $ancestor instanceof User) {
                continue;
            }

            $rows[] = [
                'user_id' => (int) $ancestor->id,
                'level' => $index + 1,
                'rebate_status' => (string) ($ancestor->rebate_status ?: 'eligible'),
                'rebate_disabled_reason' => $ancestor->rebate_disabled_reason,
            ];
        }

        return $rows;
    }

    private function treeNode(User $user, int $level, int $maxDepth): array
    {
        $node = [
            'id' => (int) $user->id,
            'username' => (string) ($user->username ?: $user->email ?: 'user_'.$user->id),
            'nickname' => (string) ($user->username ?: $user->email ?: 'user_'.$user->id),
            'level' => $level,
            'rebateStatus' => (string) ($user->rebate_status ?: 'eligible'),
            'rebateDisabledReason' => $user->rebate_disabled_reason,
            'children' => [],
        ];

        if ($level >= $maxDepth) {
            return $node;
        }

        $children = DB::table('referral_paths')
            ->where('parent_user_id', $user->id)
            ->orderBy('created_at')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $users = User::query()
            ->whereIn('id', $children)
            ->get()
            ->keyBy('id');

        foreach ($children as $id) {
            $child = $users->get($id);
            if ($child instanceof User) {
                $node['children'][] = $this->treeNode($child, $level + 1, $maxDepth);
            }
        }

        return $node;
    }

    private function refreshChildren(int $parentId, string $parentPath, int $parentDepth): void
    {
        $queue = [[$parentId, $parentPath, $parentDepth]];

        while ($queue !== []) {
            [$pid, $pathBase, $depthBase] = array_shift($queue);
            $children = DB::table('referral_paths')
                ->where('parent_user_id', $pid)
                ->orderBy('user_id')
                ->get();

            foreach ($children as $child) {
                $path = trim((string) $pathBase, '/').'/'.$child->user_id;
                $depth = (int) $depthBase + 1;

                DB::table('referral_paths')
                    ->where('user_id', $child->user_id)
                    ->update([
                        'path' => $path,
                        'depth' => $depth,
                        'updated_at' => now(),
                    ]);

                $queue[] = [(int) $child->user_id, $path, $depth];
            }
        }
    }

    private function detachReferralBranch(int $rootId): void
    {
        $root = DB::table('referral_paths')->where('user_id', $rootId)->first();
        if ($root === null) {
            return;
        }

        $prefix = trim((string) $root->path, '/').'/';
        DB::table('referral_paths')
            ->where('path', 'like', $prefix.'%')
            ->orWhere('user_id', $rootId)
            ->delete();
    }

    private function directCount(int $userId): int
    {
        return DB::table('referral_paths')
            ->where('parent_user_id', $userId)
            ->count();
    }

    private function teamCount(int $userId): int
    {
        $ref = DB::table('referral_paths')->where('user_id', $userId)->first();
        if ($ref === null) {
            return 0;
        }

        return DB::table('referral_paths')
            ->where('path', 'like', trim((string) $ref->path, '/').'/%')
            ->count();
    }

    private function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $name = (string) ($user->username ?: $user->email ?: 'user_'.$user->id);

        return [
            'id' => (int) $user->id,
            'username' => $name,
            'nickname' => $name,
        ];
    }

    private function pathHasUser(string $path, int $userId): bool
    {
        $ids = array_map('intval', array_filter(explode('/', trim($path, '/'))));

        return in_array($userId, $ids, true);
    }

    private function makeUrl(string $template, string $code): string
    {
        if ($code === '') {
            return '';
        }

        return str_replace('{code}', rawurlencode($code), $template);
    }

    private function sub2User(int $id): ?Sub2ApiUserData
    {
        $status = $this->sub2UserStatus($id);

        return $status instanceof Sub2ApiUserData ? $status : null;
    }

    private function sub2UserFor(User $user): ?Sub2ApiUserData
    {
        $sub2User = $this->sub2User((int) $user->id);
        if ($sub2User instanceof Sub2ApiUserData) {
            return $sub2User;
        }

        foreach ([(string) $user->email, (string) $user->username] as $account) {
            $account = trim($account);
            if ($account === '') {
                continue;
            }

            try {
                $sub2User = $this->sub2Users->findByAccount($account);
            } catch (Throwable) {
                $sub2User = null;
            }

            if ($sub2User instanceof Sub2ApiUserData) {
                return $sub2User;
            }
        }

        return null;
    }

    private function sub2ApiAffCode(User $user): string
    {
        try {
            $res = $this->sub2Api->affiliateOverview((int) $user->id);
        } catch (Throwable) {
            return '';
        }

        $code = trim((string) data_get($res, 'data.aff_code', ''));
        if ($code !== '') {
            $user->forceFill(['sub2api_aff_code' => $code])->save();
        }

        return $code;
    }

    private function sub2UserStatus(int $id): Sub2ApiUserData|string
    {
        try {
            return $this->sub2Users->findById($id) ?? self::SUB2API_MISSING;
        } catch (Throwable) {
            return self::SUB2API_ERROR;
        }
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatTime(mixed $value): string
    {
        return $value === null || $value === '' ? '' : (string) $value;
    }
}
