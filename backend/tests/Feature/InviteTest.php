<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_me_creates_code_and_returns_sub2api_affiliate_link(): void
    {
        $this->fakeSub2Users([
            1001 => $this->sub2User(1001, 'demo@example.com', 'demo', 'SUB2AFF12'),
        ]);
        $user = $this->user(1001, 'demo', 'demo@example.com', 'SUB2AFF12');

        $this->actingAs($user)
            ->getJson('/api/v1/invite/me')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.sub2ApiAffCode', 'SUB2AFF12')
            ->assertJsonPath('data.sub2ApiInviteUrl', 'https://api.sjiaa.cc.cd/register?aff=SUB2AFF12')
            ->assertJsonPath('data.depth', 0)
            ->assertJsonPath('data.directInviteCount', 0)
            ->assertJsonPath('data.teamInviteCount', 0)
            ->assertJsonStructure([
                'data' => [
                    'inviteCode',
                    'inviteUrl',
                    'sub2ApiAffiliatePageUrl',
                ],
            ]);

        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 1001,
            'parent_user_id' => null,
            'path' => '1001',
            'depth' => 0,
        ]);
    }

    public function test_bind_invite_code_builds_ancestor_chain_tree_and_records(): void
    {
        $this->fakeSub2Users([]);
        $a = $this->user(1001, 'a');
        $b = $this->user(1002, 'b');
        $c = $this->user(1003, 'c');

        $aCode = $this->inviteCode($a);
        $this->actingAs($b)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $aCode])
            ->assertOk()
            ->assertJsonPath('data.bound', true)
            ->assertJsonPath('data.parent.id', 1001);

        $bCode = $this->inviteCode($b);
        $this->actingAs($c)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $bCode])
            ->assertOk()
            ->assertJsonPath('data.parent.id', 1002);

        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 1003,
            'parent_user_id' => 1002,
            'path' => '1001/1002/1003',
            'depth' => 2,
        ]);

        $ancestors = app(InviteService::class)->ancestorIds($c);
        $this->assertSame([1002, 1001], $ancestors);

        $this->actingAs($a)
            ->getJson('/api/v1/invite/tree?maxDepth=3')
            ->assertOk()
            ->assertJsonPath('data.root.id', 1001)
            ->assertJsonPath('data.root.children.0.id', 1002)
            ->assertJsonPath('data.root.children.0.children.0.id', 1003);

        $this->actingAs($a)
            ->getJson('/api/v1/invite/records?page=1&pageSize=20')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.list.0.id', 1002)
            ->assertJsonPath('data.list.0.level', 1)
            ->assertJsonPath('data.list.1.id', 1003)
            ->assertJsonPath('data.list.1.level', 2);
    }

    public function test_bind_rejects_self_invite_and_rebind(): void
    {
        $this->fakeSub2Users([]);
        $a = $this->user(1001, 'a');
        $b = $this->user(1002, 'b');

        $aCode = $this->inviteCode($a);

        $this->actingAs($a)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $aCode])
            ->assertStatus(422)
            ->assertJsonPath('message', '不能绑定自己的邀请码');

        $this->actingAs($b)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $aCode])
            ->assertOk();

        $this->actingAs($b)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $aCode])
            ->assertStatus(422)
            ->assertJsonPath('message', '已绑定邀请关系');
    }

    public function test_invite_endpoints_require_login(): void
    {
        $this->getJson('/api/v1/invite/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', '未登录');
    }

    private function inviteCode(User $user): string
    {
        app(InviteService::class)->ensurePath($user);

        return (string) DB::table('referral_paths')->where('user_id', $user->id)->value('invite_code');
    }

    private function user(int $id, string $username, string $email = ''): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $email !== '' ? $email : $username.'@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    private function sub2User(int $id, string $email, string $username, string $affCode): Sub2ApiUserData
    {
        return new Sub2ApiUserData(
            id: $id,
            email: $email,
            username: $username,
            passwordHash: Hash::make('secret123'),
            role: 'user',
            status: 'active',
            balance: '0',
            totalRecharged: '0',
            affCode: $affCode,
            inviterId: null,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );
    }

    /**
     * @param array<int, Sub2ApiUserData> $users
     */
    private function fakeSub2Users(array $users): void
    {
        $this->app->instance(Sub2ApiUserRepository::class, new class($users) extends Sub2ApiUserRepository {
            /**
             * @param array<int, Sub2ApiUserData> $users
             */
            public function __construct(private readonly array $users)
            {
            }

            public function findByAccount(string $account): ?Sub2ApiUserData
            {
                foreach ($this->users as $user) {
                    if (in_array($account, [$user->email, $user->username], true)) {
                        return $user;
                    }
                }

                return null;
            }

            public function findById(int $id): ?Sub2ApiUserData
            {
                return $this->users[$id] ?? null;
            }

            public function identityProviders(int $userId): array
            {
                return ['email'];
            }
        });
    }
}
