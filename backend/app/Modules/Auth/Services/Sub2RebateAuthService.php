<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        } catch (Throwable) {
            $sub2User = null;
        }

        if ($sub2User !== null && $sub2User->passwordHash !== '') {
            if ($sub2User->status !== 'active') {
                return [
                    'error' => 'disabled',
                ];
            }

            if (password_verify($password, $sub2User->passwordHash)) {
                $user = $this->syncLocalUser($sub2User);

                return [
                    'user' => $user,
                    'sub2User' => $sub2User,
                ];
            }
        }

        $remote = $this->remoteValidate($account, $password);
        if ($remote !== null) {
            return $remote;
        }

        return $this->localValidate($account, $password);
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

    private function remoteValidate(string $account, string $password): ?array
    {
        $baseUrl = rtrim((string) config('sub2rebate.sub2api_base_url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        try {
            $login = Http::baseUrl($baseUrl)
                ->timeout((int) config('sub2rebate.sub2api_admin_timeout', 10))
                ->acceptJson()
                ->post('/api/v1/auth/login', [
                    'email' => $account,
                    'password' => $password,
                ]);

            if (! $login->successful()) {
                return null;
            }

            $token = (string) (
                data_get($login->json(), 'data.access_token')
                ?: data_get($login->json(), 'data.token')
            );
            if ($token === '') {
                return null;
            }

            $me = Http::baseUrl($baseUrl)
                ->timeout((int) config('sub2rebate.sub2api_admin_timeout', 10))
                ->acceptJson()
                ->withToken($token)
                ->get('/api/v1/auth/me');

            if (! $me->successful()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $data = data_get($me->json(), 'data');
        if (is_array(data_get($data, 'user'))) {
            $data = data_get($data, 'user');
        }

        if (! is_array($data) || ! is_numeric($data['id'] ?? null)) {
            return null;
        }

        $status = (string) ($data['status'] ?? 'active');
        if ($status !== 'active') {
            return [
                'error' => 'disabled',
            ];
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' && str_contains($account, '@')) {
            $email = $account;
        }

        $sub2User = new Sub2ApiUserData(
            id: (int) $data['id'],
            email: $email,
            username: (string) ($data['username'] ?? ''),
            passwordHash: '',
            role: (string) ($data['role'] ?? 'user'),
            status: $status,
            balance: (string) ($data['balance'] ?? '0'),
            totalRecharged: (string) ($data['total_recharged'] ?? '0'),
            affCode: isset($data['aff_code']) ? (string) $data['aff_code'] : null,
            inviterId: is_numeric($data['inviter_id'] ?? null) ? (int) $data['inviter_id'] : null,
            createdAt: $this->time($data['created_at'] ?? null),
            updatedAt: $this->time($data['updated_at'] ?? null),
        );

        $user = $this->syncLocalUser($sub2User);

        return [
            'user' => $user,
            'sub2User' => $sub2User,
        ];
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->timezone('Asia/Shanghai');
    }
}
