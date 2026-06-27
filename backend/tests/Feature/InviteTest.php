<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
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

    public function test_invite_me_uses_sub2api_affiliate_overview_when_readonly_user_is_missing(): void
    {
        $this->fakeSub2Users([]);
        $this->app->instance(Sub2ApiAdminClient::class, new class extends Sub2ApiAdminClient {
            public function affiliateOverview(string|int $id): array
            {
                return [
                    'code' => 0,
                    'data' => [
                        'user_id' => (int) $id,
                        'aff_code' => 'OVERVIEW12',
                    ],
                ];
            }
        });

        $user = $this->user(1101, 'overview', 'overview@example.com');

        $this->actingAs($user)
            ->getJson('/api/v1/invite/me')
            ->assertOk()
            ->assertJsonPath('data.sub2ApiAffCode', 'OVERVIEW12')
            ->assertJsonPath('data.sub2ApiInviteUrl', 'https://api.sjiaa.cc.cd/register?aff=OVERVIEW12');

        $this->assertDatabaseHas('users', [
            'id' => 1101,
            'sub2api_aff_code' => 'OVERVIEW12',
        ]);
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

    public function test_query_refreshes_sub2api_team_and_removes_deleted_invite_branch(): void
    {
        $root = $this->user(2001, 'root', 'root@example.com', 'AFFROOT');
        $live = $this->user(2002, 'live', 'live@example.com', 'AFFLIVE');
        $deleted = $this->user(2003, 'deleted', 'deleted@example.com', 'AFFDEL');
        $leaf = $this->user(2004, 'leaf', 'leaf@example.com', 'AFFLEAF');

        DB::table('referral_paths')->insert([
            [
                'user_id' => $root->id,
                'parent_user_id' => null,
                'invite_code' => 'AFFROOT',
                'path' => '2001',
                'depth' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $live->id,
                'parent_user_id' => $root->id,
                'invite_code' => 'AFFLIVE',
                'path' => '2001/2002',
                'depth' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $deleted->id,
                'parent_user_id' => $root->id,
                'invite_code' => 'AFFDEL',
                'path' => '2001/2003',
                'depth' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $leaf->id,
                'parent_user_id' => $deleted->id,
                'invite_code' => 'AFFLEAF',
                'path' => '2001/2003/2004',
                'depth' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->fakeSub2Users([
            2001 => $this->sub2User(2001, 'root@example.com', 'root', 'AFFROOT'),
            2002 => $this->sub2User(2002, 'live@example.com', 'live', 'AFFLIVE', 2001),
        ]);

        $this->actingAs($root)
            ->getJson('/api/v1/invite/tree?maxDepth=3')
            ->assertOk()
            ->assertJsonPath('data.root.children.0.id', 2002)
            ->assertJsonMissingPath('data.root.children.1');

        $this->assertDatabaseHas('referral_paths', ['user_id' => 2002, 'parent_user_id' => 2001]);
        $this->assertDatabaseMissing('referral_paths', ['user_id' => 2003]);
        $this->assertDatabaseMissing('referral_paths', ['user_id' => 2004]);

        $this->actingAs($root)
            ->getJson('/api/v1/invite/records?page=1&pageSize=20')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.id', 2002);
    }

    public function test_query_keeps_local_branch_when_sub2api_child_lookup_fails(): void
    {
        $root = $this->user(3001, 'root', 'root@example.com', 'AFFROOT');
        $child = $this->user(3002, 'child', 'child@example.com', 'AFFCHILD');

        DB::table('referral_paths')->insert([
            [
                'user_id' => $root->id,
                'parent_user_id' => null,
                'invite_code' => 'AFFROOT',
                'path' => '3001',
                'depth' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $child->id,
                'parent_user_id' => $root->id,
                'invite_code' => 'AFFCHILD',
                'path' => '3001/3002',
                'depth' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->fakeSub2UsersWithErrorIds([
            3001 => $this->sub2User(3001, 'root@example.com', 'root', 'AFFROOT'),
        ], [3002]);

        $this->actingAs($root)
            ->getJson('/api/v1/invite/tree?maxDepth=3')
            ->assertOk()
            ->assertJsonPath('data.root.children.0.id', 3002);

        $this->assertDatabaseHas('referral_paths', ['user_id' => 3002, 'parent_user_id' => 3001]);
    }

    public function test_query_refreshes_sub2api_team_and_discovers_new_children(): void
    {
        $root = $this->user(4001, 'root', 'root@example.com', 'AFFROOT');
        DB::table('referral_paths')->insert([
            'user_id' => $root->id,
            'parent_user_id' => null,
            'invite_code' => 'AFFROOT',
            'path' => '4001',
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->fakeSub2Users([
            4001 => $this->sub2User(4001, 'root@example.com', 'root', 'AFFROOT'),
            4002 => $this->sub2User(4002, 'child@example.com', 'child', 'AFFCHILD', 4001),
            4003 => $this->sub2User(4003, 'leaf@example.com', 'leaf', 'AFFLEAF', 4002),
        ]);

        $this->actingAs($root)
            ->getJson('/api/v1/invite/tree?maxDepth=3')
            ->assertOk()
            ->assertJsonPath('data.root.children.0.id', 4002)
            ->assertJsonPath('data.root.children.0.children.0.id', 4003);

        $this->assertDatabaseHas('users', ['id' => 4002, 'email' => 'child@example.com']);
        $this->assertDatabaseHas('users', ['id' => 4003, 'email' => 'leaf@example.com']);
        $this->assertDatabaseHas('referral_paths', ['user_id' => 4002, 'parent_user_id' => 4001, 'path' => '4001/4002']);
        $this->assertDatabaseHas('referral_paths', ['user_id' => 4003, 'parent_user_id' => 4002, 'path' => '4001/4002/4003']);
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

    private function user(int $id, string $username, string $email = '', string $affCode = ''): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $email !== '' ? $email : $username.'@example.com',
            'role' => 'user',
            'status' => 'active',
            'sub2api_aff_code' => $affCode !== '' ? $affCode : null,
        ]);
    }

    private function sub2User(int $id, string $email, string $username, string $affCode, ?int $inviterId = null): Sub2ApiUserData
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
            inviterId: $inviterId,
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

            public function childrenOf(int $inviterId): array
            {
                return array_values(array_filter(
                    $this->users,
                    fn (Sub2ApiUserData $user): bool => $user->inviterId === $inviterId
                ));
            }

            public function identityProviders(int $userId): array
            {
                return ['email'];
            }
        });
    }

    /**
     * @param array<int, Sub2ApiUserData> $users
     * @param array<int> $errorIds
     */
    private function fakeSub2UsersWithErrorIds(array $users, array $errorIds): void
    {
        $this->app->instance(Sub2ApiUserRepository::class, new class($users, $errorIds) extends Sub2ApiUserRepository {
            /**
             * @param array<int, Sub2ApiUserData> $users
             * @param array<int> $errorIds
             */
            public function __construct(private readonly array $users, private readonly array $errorIds)
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
                if (in_array($id, $this->errorIds, true)) {
                    throw new RuntimeException('sub2api lookup failed');
                }

                return $this->users[$id] ?? null;
            }

            public function childrenOf(int $inviterId): array
            {
                return array_values(array_filter(
                    $this->users,
                    fn (Sub2ApiUserData $user): bool => $user->inviterId === $inviterId
                ));
            }

            public function identityProviders(int $userId): array
            {
                return ['email'];
            }
        });
    }
}
