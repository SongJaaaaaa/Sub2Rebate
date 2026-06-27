<?php

namespace Tests\Feature;

use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_syncs_sub2api_user_and_issues_token(): void
    {
        $this->fakeSub2ApiUser();

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonPath('data.user.id', 1001)
            ->assertJsonPath('data.user.email', 'demo@example.com')
            ->assertJsonStructure([
                'data' => [
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'email' => 'demo@example.com',
            'role' => 'user',
            'status' => 'active',
            'sub2api_aff_code' => 'SUB2AFF12',
        ]);
    }

    public function test_login_can_sync_remote_sub2api_user(): void
    {
        config(['sub2rebate.sub2api_base_url' => 'https://sub2api.test']);

        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'code' => 0,
                'data' => ['access_token' => 'remote-token'],
            ]),
            'https://sub2api.test/api/v1/auth/me' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1,
                    'email' => 'Song@qq.com',
                    'username' => '',
                    'role' => 'admin',
                    'status' => 'active',
                    'balance' => 100,
                    'total_recharged' => 10,
                ],
            ]),
        ]);

        $this->app->instance(Sub2ApiUserRepository::class, new class extends Sub2ApiUserRepository {
            public function findByAccount(string $account): ?Sub2ApiUserData
            {
                return null;
            }

            public function findById(int $id): ?Sub2ApiUserData
            {
                return null;
            }
        });

        $this->postJson('/api/v1/auth/login', [
            'account' => 'Song@qq.com',
            'password' => 'song123',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', 1)
            ->assertJsonPath('data.user.email', 'Song@qq.com')
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertDatabaseHas('users', [
            'id' => 1,
            'email' => 'Song@qq.com',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_profile_returns_sub2rebate_and_sub2api_invite_links(): void
    {
        $this->fakeSub2ApiUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'account' => 'demo',
            'password' => 'secret123',
        ])->json('data');

        RebateBalance::query()->create([
            'user_id' => 1001,
            'available_amount' => '30',
            'frozen_amount' => '5',
            'withdrawn_amount' => '10',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$login['token'])
            ->getJson('/api/v1/account/profile')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.user.id', 1001)
            ->assertJsonPath('data.invite.sub2ApiAffCode', 'SUB2AFF12')
            ->assertJsonPath('data.invite.sub2ApiInviteUrl', 'https://api.sjiaa.cc.cd/register?aff=SUB2AFF12')
            ->assertJsonPath('data.invite.sub2ApiAffiliatePageUrl', 'https://api.sjiaa.cc.cd/affiliate')
            ->assertJsonPath('data.invite.inviteCode', '')
            ->assertJsonPath('data.invite.inviteUrl', '')
            ->assertJsonPath('data.balance.availableAmount', '30.00')
            ->assertJsonPath('data.balance.totalAmount', '35.00')
            ->assertJsonStructure([
                'data' => [
                    'balance' => [
                        'availableAmount',
                        'frozenAmount',
                        'totalAmount',
                        'withdrawnAmount',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 1001,
            'depth' => 0,
        ]);
    }

    public function test_login_syncs_sub2api_inviter_into_local_referral_path(): void
    {
        $parent = new Sub2ApiUserData(
            id: 2001,
            email: 'parent@example.com',
            username: 'parent',
            passwordHash: Hash::make('secret123'),
            role: 'user',
            status: 'active',
            balance: '0',
            totalRecharged: '0',
            affCode: 'PARENT12',
            inviterId: null,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );
        $child = new Sub2ApiUserData(
            id: 2002,
            email: 'child@example.com',
            username: 'child',
            passwordHash: Hash::make('secret123'),
            role: 'user',
            status: 'active',
            balance: '0',
            totalRecharged: '0',
            affCode: 'CHILD12',
            inviterId: 2001,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );
        $this->fakeSub2ApiUsers([$parent, $child]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'child@example.com',
            'password' => 'secret123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 2002,
            'sub2api_aff_code' => 'CHILD12',
            'sub2api_inviter_id' => 2001,
        ]);
        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 2002,
            'parent_user_id' => 2001,
            'depth' => 1,
            'path' => '2001/2002',
        ]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->fakeSub2ApiUser();

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'code' => 40102,
                'message' => '账号或密码错误',
                'data' => null,
            ]);
    }

    public function test_login_is_rate_limited(): void
    {
        config(['sub2rebate.test_rate_limit' => true]);
        $this->fakeSub2ApiUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'account' => 'demo@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_change_password_is_disabled_for_sub2api_passwords(): void
    {
        $user = $this->localUser(1001, 'demo@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/account/change-password', [
                'oldPassword' => 'secret123',
                'newPassword' => 'new-secret',
            ])
            ->assertBadRequest()
            ->assertJsonPath('message', '登录密码由 Sub2API 统一管理，请前往 Sub2API 修改密码');
    }

    public function test_update_profile_rejects_duplicate_email(): void
    {
        $user = $this->localUser(1001, 'demo@example.com');
        $this->localUser(1002, 'used@example.com');

        $this->actingAs($user)
            ->putJson('/api/v1/account/profile', [
                'email' => 'used@example.com',
            ])
            ->assertUnprocessable();
    }

    public function test_login_rejects_disabled_sub2api_user(): void
    {
        $this->fakeSub2ApiUser(status: 'disabled');

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'secret123',
        ])
            ->assertForbidden()
            ->assertJson([
                'code' => 40302,
                'message' => '账号已被禁用',
                'data' => null,
            ]);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'code' => 40101,
                'message' => '未登录',
                'data' => null,
            ]);
    }

    private function fakeSub2ApiUser(string $status = 'active'): void
    {
        $user = new Sub2ApiUserData(
            id: 1001,
            email: 'demo@example.com',
            username: 'demo',
            passwordHash: Hash::make('secret123'),
            role: 'user',
            status: $status,
            balance: '128.00000000',
            totalRecharged: '200.00000000',
            affCode: 'SUB2AFF12',
            inviterId: null,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );

        $this->fakeSub2ApiUsers([$user]);
    }

    private function localUser(int $id, string $email): \App\Models\User
    {
        return \App\Models\User::query()->create([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => $email,
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    /**
     * @param array<int, Sub2ApiUserData> $users
     */
    private function fakeSub2ApiUsers(array $users): void
    {
        $byId = [];
        $byAccount = [];
        foreach ($users as $user) {
            $byId[$user->id] = $user;
            $byAccount[mb_strtolower($user->email)] = $user;
            $byAccount[mb_strtolower($user->username)] = $user;
        }

        $this->app->instance(Sub2ApiUserRepository::class, new class($byId, $byAccount) extends Sub2ApiUserRepository {
            public function __construct(private readonly array $byId, private readonly array $byAccount)
            {
            }

            public function findByAccount(string $account): ?Sub2ApiUserData
            {
                return $this->byAccount[mb_strtolower(trim($account))] ?? null;
            }

            public function findById(int $id): ?Sub2ApiUserData
            {
                return $this->byId[$id] ?? null;
            }

            public function identityProviders(int $userId): array
            {
                return ['email'];
            }
        });
    }
}
