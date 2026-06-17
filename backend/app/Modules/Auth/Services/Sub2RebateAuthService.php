<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class Sub2RebateAuthService
{
    public function __construct(
        private readonly Sub2ApiUserRepository $users,
        private readonly InviteService $invites,
    ) {
    }

    public function attempt(string $account, string $password): ?array
    {
        $result = $this->validate($account, $password);
        if ($result === null || ($result['error'] ?? null) === 'disabled') {
            return $result;
        }

        /** @var User $user */
        $user = $result['user'];
        $token = $user->createToken('sub2rebate-user')->plainTextToken;

        return [
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $user,
            'sub2User' => $result['sub2User'],
        ];
    }

    public function validate(string $account, string $password): ?array
    {
        try {
            $sub2User = $this->users->findByAccount($account);
        } catch (Throwable $e) {
            if (! app()->environment(['local', 'testing'])) {
                throw $e;
            }

            return $this->localValidate($account, $password);
        }

        if ($sub2User === null || $sub2User->passwordHash === '') {
            return $this->localValidate($account, $password);
        }

        if ($sub2User->status !== 'active') {
            return [
                'error' => 'disabled',
            ];
        }

        if (! password_verify($password, $sub2User->passwordHash)) {
            return $this->localValidate($account, $password);
        }

        $user = $this->syncLocalUser($sub2User);

        return [
            'user' => $user,
            'sub2User' => $sub2User,
        ];
    }

    public function syncLocalUser(Sub2ApiUserData $sub2User): User
    {
        $role = $this->resolveRole($sub2User);

        $user = User::query()->updateOrCreate(
            ['id' => $sub2User->id],
            [
                'username' => $sub2User->username,
                'email' => $sub2User->email,
                'role' => $role,
                'status' => $sub2User->status,
                'sub2api_aff_code' => $sub2User->affCode,
                'sub2api_inviter_id' => $sub2User->inviterId,
            ]
        );

        $this->invites->syncFromSub2Api($user);

        return $user;
    }

    private function resolveRole(Sub2ApiUserData $sub2User): string
    {
        $role = DB::table('sub2_user_roles')
            ->where('user_id', $sub2User->id)
            ->value('role');

        $role = is_string($role) && $role !== '' ? $role : $sub2User->role;

        return in_array($role, ['user', 'admin'], true) ? $role : 'user';
    }

    private function localValidate(string $account, string $password): ?array
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $val = mb_strtolower(trim($account));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$val])
            ->orWhereRaw('LOWER(username) = ?', [$val])
            ->first();

        if (! $user instanceof User || (string) $user->password === '') {
            return null;
        }

        if ($user->status !== 'active') {
            return [
                'error' => 'disabled',
            ];
        }

        if (! Hash::check($password, (string) $user->password)) {
            return null;
        }

        $this->invites->syncFromSub2Api($user);

        return [
            'user' => $user,
            'sub2User' => null,
        ];
    }
}
