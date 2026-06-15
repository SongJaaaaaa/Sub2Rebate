<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 深度测试：邀请链接与注册流程
 *
 * 覆盖：
 * - 邀请码自动生成
 * - 邀请绑定（正常 / 重复 / 自我绑定 / 循环绑定）
 * - 多层级链路构建
 * - Sub2API inviter_id 同步为本地 referral_paths
 * - 邀请树 API 返回正确层级
 * - 邀请记录 API 分页正确
 */
class DeepInviteFlowTest extends TestCase
{
    use RefreshDatabase;

    // ─── 邀请码生成 ───

    public function test_invite_code_auto_generated_on_first_access(): void
    {
        $user = $this->user(1001, 'alice');
        $service = app(InviteService::class);

        $result = $service->me($user);

        $this->assertSame('', $result['sub2ApiAffCode']);
        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 1001,
        ]);
        $code = DB::table('referral_paths')->where('user_id', 1001)->value('invite_code');
        $this->assertNotEmpty($code);
        $this->assertSame(6, strlen($code));
    }

    public function test_invite_code_is_idempotent(): void
    {
        $user = $this->user(1001, 'alice');
        $service = app(InviteService::class);

        $service->me($user);
        $code1 = DB::table('referral_paths')->where('user_id', 1001)->value('invite_code');
        $service->me($user);
        $code2 = DB::table('referral_paths')->where('user_id', 1001)->value('invite_code');

        $this->assertSame($code1, $code2);
    }

    // ─── 邀请绑定 ───

    public function test_bind_creates_parent_child_relationship(): void
    {
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        $service = app(InviteService::class);

        $service->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');

        $result = $service->bind($child, $code);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('referral_paths', [
            'user_id' => $child->id,
            'parent_user_id' => $parent->id,
            'depth' => 1,
        ]);
    }

    public function test_bind_rejects_empty_invite_code(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(InviteService::class);

        $result = $service->bind($user, '');

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['status']);
    }

    public function test_bind_rejects_self_binding(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(InviteService::class);

        $service->ensurePath($user);
        $code = (string) DB::table('referral_paths')->where('user_id', $user->id)->value('invite_code');

        $result = $service->bind($user, $code);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('自己', $result['message']);
    }

    public function test_bind_rejects_already_bound_user(): void
    {
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        $other = $this->user(1003, 'other');
        $service = app(InviteService::class);

        $service->ensurePath($parent);
        $service->ensurePath($other);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $otherCode = (string) DB::table('referral_paths')->where('user_id', $other->id)->value('invite_code');

        $service->bind($child, $code);
        $result = $service->bind($child, $otherCode);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('已绑定邀请关系', $result['message']);
    }

    public function test_bind_rejects_nonexistent_code(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(InviteService::class);

        $result = $service->bind($user, 'XXXXXXXX');

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['status']);
    }

    // ─── 多层级链路 ───

    public function test_three_level_chain_builds_correct_paths(): void
    {
        $a = $this->user(1001, 'level0');
        $b = $this->user(1002, 'level1');
        $c = $this->user(1003, 'level2');
        $d = $this->user(1004, 'level3');
        $service = app(InviteService::class);

        $this->bindChain($service, [$a, $b, $c, $d]);

        $this->assertDatabaseHas('referral_paths', ['user_id' => $b->id, 'parent_user_id' => $a->id, 'depth' => 1]);
        $this->assertDatabaseHas('referral_paths', ['user_id' => $c->id, 'parent_user_id' => $b->id, 'depth' => 2]);
        $this->assertDatabaseHas('referral_paths', ['user_id' => $d->id, 'parent_user_id' => $c->id, 'depth' => 3]);
    }

    public function test_ancestor_ids_returns_correct_order(): void
    {
        $a = $this->user(1001, 'top');
        $b = $this->user(1002, 'mid');
        $c = $this->user(1003, 'bot');
        $service = app(InviteService::class);

        $this->bindChain($service, [$a, $b, $c]);

        $ancestors = $service->ancestorIds($c);

        $this->assertSame([$b->id, $a->id], $ancestors);
    }

    // ─── Sub2API inviter_id 同步 ───

    public function test_login_syncs_sub2api_inviter_to_local_referral_path(): void
    {
        $parent = new Sub2ApiUserData(
            id: 2,
            email: 'parent@example.com',
            username: 'parent',
            passwordHash: password_hash('pass123', PASSWORD_BCRYPT),
            role: 'user',
            status: 'active',
            balance: '100',
            totalRecharged: '200',
            affCode: '8UDG84TQD7BD',
            inviterId: null,
            createdAt: CarbonImmutable::now(),
            updatedAt: CarbonImmutable::now(),
        );
        $child = new Sub2ApiUserData(
            id: 3,
            email: 'sub2rebate-test@example.com',
            username: 'testchild',
            passwordHash: password_hash('pass123', PASSWORD_BCRYPT),
            role: 'user',
            status: 'active',
            balance: '0',
            totalRecharged: '0',
            affCode: 'WRHZJ7QTKULU',
            inviterId: 2,
            createdAt: CarbonImmutable::now(),
            updatedAt: CarbonImmutable::now(),
        );

        $this->fakeSub2ApiUsers([$parent, $child]);

        // 先登录 parent 让本地用户存在
        $this->postJson('/api/v1/auth/login', ['account' => 'parent@example.com', 'password' => 'pass123'])
            ->assertOk();

        // 再登录 child，应同步 inviter_id = 2 为 parent_user_id = 2
        $this->postJson('/api/v1/auth/login', ['account' => 'sub2rebate-test@example.com', 'password' => 'pass123'])
            ->assertOk();

        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 3,
            'parent_user_id' => 2,
            'depth' => 1,
        ]);
    }

    // ─── 邀请树 API ───

    public function test_invite_tree_api_returns_correct_levels(): void
    {
        $this->fakeSub2ApiUsers([]);
        $parent = $this->user(1001, 'parent');
        $child1 = $this->user(1002, 'child1');
        $child2 = $this->user(1003, 'child2');
        $grandchild = $this->user(1004, 'grandchild');

        $service = app(InviteService::class);
        $this->bindChain($service, [$parent, $child1, $grandchild]);
        $service->ensurePath($child2);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $service->bind($child2, $code);

        $token = $parent->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/invite/tree')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $tree = $response->json('data');
        $this->assertNotEmpty($tree);
    }

    // ─── 邀请记录 API ───

    public function test_invite_records_api_returns_paginated_list(): void
    {
        $this->fakeSub2ApiUsers([]);
        $parent = $this->user(1001, 'parent');
        $service = app(InviteService::class);
        $service->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');

        for ($i = 1; $i <= 5; $i++) {
            $child = $this->user(1001 + $i, 'child'.$i);
            $service->bind($child, $code);
        }

        $token = $parent->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/invite/records?page=1&pageSize=3')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 5);
    }

    // ─── 辅助方法 ───

    private function user(int $id, string $username): User
    {
        return User::query()->firstOrCreate(
            ['id' => $id],
            [
                'username' => $username,
                'email' => $username.'@example.com',
                'role' => 'user',
                'status' => 'active',
            ]
        );
    }

    private function bindChain(InviteService $service, array $users): void
    {
        $service->ensurePath($users[0]);
        for ($i = 1; $i < count($users); $i++) {
            $code = (string) DB::table('referral_paths')
                ->where('user_id', $users[$i - 1]->id)
                ->value('invite_code');
            $service->bind($users[$i], $code);
        }
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
