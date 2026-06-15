<?php

namespace App\Modules\User\Services;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class AccountProfileService
{
    public function __construct(
        private readonly Sub2ApiUserRepository $sub2Users,
        private readonly InviteService $invites,
    ) {
    }

    public function me(User $user): array
    {
        $profile = $this->profile($user);

        return [
            'user' => $profile['user'],
            'balance' => $profile['balance'],
        ];
    }

    public function profile(User $user): array
    {
        $sub2User = $this->sub2User((int) $user->id);
        if ($sub2User !== null) {
            $this->syncUserSnapshot($user, $sub2User);
            $user->refresh();
        }

        $ref = $this->invites->syncFromSub2Api($user);

        return [
            'user' => $this->userPayload($user, $sub2User),
            'invite' => [
                'inviteCode' => '',
                'inviteUrl' => '',
                'sub2ApiAffCode' => $sub2User?->affCode ?? '',
                'sub2ApiInviteUrl' => $this->makeUrl((string) config('sub2rebate.sub2api_invite_url_template'), $sub2User?->affCode ?? ''),
                'sub2ApiAffiliatePageUrl' => (string) config('sub2rebate.sub2api_affiliate_page_url'),
                'parentNickname' => $this->parentNickname($ref),
                'depth' => (int) $ref->depth,
            ],
            'balance' => $this->emptyBalance(),
        ];
    }

    public function userPayload(User $user, ?Sub2ApiUserData $sub2User = null): array
    {
        $username = $sub2User?->username ?: (string) $user->username;
        $email = $sub2User?->email ?: (string) $user->email;

        if ($username === '') {
            $username = $email !== '' ? Str::before($email, '@') : 'user_'.$user->id;
        }

        return [
            'id' => (int) $user->id,
            'username' => $username,
            'nickname' => $username,
            'email' => $email,
            'avatar' => '',
            'role' => in_array($user->role, ['user', 'admin'], true) ? $user->role : 'user',
            'createdAt' => $this->formatTime($sub2User?->createdAt ?: $user->created_at),
        ];
    }

    private function syncUserSnapshot(User $user, Sub2ApiUserData $sub2User): void
    {
        $role = $this->resolveRole($sub2User);

        $user->forceFill([
            'username' => $sub2User->username,
            'email' => $sub2User->email,
            'role' => $role,
            'status' => $sub2User->status,
            'sub2api_aff_code' => $sub2User->affCode,
            'sub2api_inviter_id' => $sub2User->inviterId,
        ])->save();
    }

    private function resolveRole(Sub2ApiUserData $sub2User): string
    {
        $role = DB::table('sub2_user_roles')
            ->where('user_id', $sub2User->id)
            ->value('role');

        $role = is_string($role) && $role !== '' ? $role : $sub2User->role;

        return in_array($role, ['user', 'admin'], true) ? $role : 'user';
    }

    private function parentNickname(stdClass $ref): ?string
    {
        if ($ref->parent_user_id === null) {
            return null;
        }

        $parent = User::query()->find((int) $ref->parent_user_id);
        if ($parent !== null) {
            return $parent->username ?: $parent->email;
        }

        $sub2Parent = $this->sub2User((int) $ref->parent_user_id);

        return $sub2Parent?->username ?: $sub2Parent?->email;
    }

    private function sub2User(int $id): ?Sub2ApiUserData
    {
        try {
            return $this->sub2Users->findById($id);
        } catch (Throwable) {
            return null;
        }
    }

    private function makeUrl(string $template, string $code): string
    {
        if ($code === '') {
            return '';
        }

        return str_replace('{code}', rawurlencode($code), $template);
    }

    private function emptyBalance(): array
    {
        return [
            'availableAmount' => $this->money(0),
            'frozenAmount' => $this->money(0),
            'totalAmount' => $this->money(0),
            'withdrawnAmount' => $this->money(0),
        ];
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatTime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $value instanceof CarbonInterface
            ? $value->timezone('Asia/Shanghai')->format('Y-m-d H:i:s')
            : (string) $value;
    }
}
