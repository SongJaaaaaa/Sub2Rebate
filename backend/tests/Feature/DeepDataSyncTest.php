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
 * 深度测试：Sub2API → Sub2Rebate 数据同步一致性
 *
 * 覆盖：
 * - 登录时同步用户基本信息
 * - 登录时同步 inviter_id 为本地 referral_paths
 * - 多次登录不重复创建关系
 * - inviter_id 变化时的处理
 * - 角色同步逻辑（sub2_user_roles 优先）
 * - 用户状态同步
 */
class DeepDataSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_local_user_with_sub2api_fields(): void
    {
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'alice', 'alice@test.com', 'AFFCODE1', null),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'alice@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'username' => 'alice',
            'email' => 'alice@test.com',
            'sub2api_aff_code' => 'AFFCODE1',
            'sub2api_inviter_id' => null,
            'status' => 'active',
            'role' => 'user',
        ]);
    }

    public function test_login_syncs_inviter_id_to_referral_paths(): void
    {
        // 先创建 parent 用户（模拟之前已登录过）
        User::query()->create([
            'id' => 2,
            'username' => 'parent',
            'email' => 'parent@test.com',
            'role' => 'user',
            'status' => 'active',
            'sub2api_aff_code' => '8UDG84TQD7BD',
        ]);
        app(InviteService::class)->ensurePath(User::query()->find(2));

        // 子用户登录，带有 inviter_id = 2
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(3, 'child', 'child@test.com', 'WRHZJ7QTKULU', 2),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'child@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 3,
            'sub2api_inviter_id' => 2,
        ]);
        $this->assertDatabaseHas('referral_paths', [
            'user_id' => 3,
            'parent_user_id' => 2,
            'depth' => 1,
        ]);
    }

    public function test_repeated_login_does_not_duplicate_referral_path(): void
    {
        User::query()->create([
            'id' => 2,
            'username' => 'parent',
            'email' => 'parent@test.com',
            'role' => 'user',
            'status' => 'active',
        ]);
        app(InviteService::class)->ensurePath(User::query()->find(2));

        $this->fakeSub2ApiUsers([
            $this->makeSub2User(3, 'child', 'child@test.com', 'AFF3', 2),
        ]);

        // 登录两次
        $this->postJson('/api/v1/auth/login', ['account' => 'child@test.com', 'password' => 'password123'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['account' => 'child@test.com', 'password' => 'password123'])->assertOk();

        $count = DB::table('referral_paths')->where('user_id', 3)->count();
        $this->assertSame(1, $count);
    }

    public function test_role_from_sub2_user_roles_table_takes_precedence(): void
    {
        User::query()->create([
            'id' => 1001,
            'username' => 'alice',
            'email' => 'alice@test.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        // sub2_user_roles 表覆盖角色
        DB::table('sub2_user_roles')->insert([
            'user_id' => 1001,
            'role' => 'admin',
        ]);

        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'alice', 'alice@test.com', 'AFF1', null, 'user'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'alice@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'role' => 'admin',
        ]);
    }

    public function test_role_defaults_to_sub2api_role_when_no_override(): void
    {
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'alice', 'alice@test.com', 'AFF1', null, 'admin'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'alice@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'role' => 'admin',
        ]);
    }

    public function test_invalid_role_defaults_to_user(): void
    {
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'alice', 'alice@test.com', 'AFF1', null, 'superadmin'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'alice@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'role' => 'user', // 无效角色被钳位为 user
        ]);
    }

    public function test_user_status_synced_from_sub2api(): void
    {
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'alice', 'alice@test.com', 'AFF1', null, 'user', 'active'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'alice@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'status' => 'active',
        ]);
    }

    public function test_login_updates_existing_user_fields(): void
    {
        // 先创建本地用户（旧数据）
        User::query()->create([
            'id' => 1001,
            'username' => 'old_name',
            'email' => 'old@test.com',
            'role' => 'user',
            'status' => 'active',
            'sub2api_aff_code' => 'OLD_CODE',
        ]);

        // Sub2API 返回新数据
        $this->fakeSub2ApiUsers([
            $this->makeSub2User(1001, 'new_name', 'new@test.com', 'NEW_CODE', null),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'account' => 'new@test.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => 1001,
            'username' => 'new_name',
            'email' => 'new@test.com',
            'sub2api_aff_code' => 'NEW_CODE',
        ]);
    }

    public function test_three_level_sync_chain_from_sub2api(): void
    {
        // 模拟三层邀请链 A → B → C，按登录顺序依次同步
        User::query()->create(['id' => 1, 'username' => 'root', 'email' => 'root@test.com', 'role' => 'user', 'status' => 'active']);
        app(InviteService::class)->ensurePath(User::query()->find(1));

        $mid = $this->makeSub2User(2, 'mid', 'mid@test.com', 'AFF2', 1);
        $leaf = $this->makeSub2User(3, 'leaf', 'leaf@test.com', 'AFF3', 2);
        $this->fakeSub2ApiUsers([$mid, $leaf]);

        // B 登录（inviter = A）
        $this->postJson('/api/v1/auth/login', ['account' => 'mid@test.com', 'password' => 'password123'])->assertOk();

        $this->assertDatabaseHas('referral_paths', ['user_id' => 2, 'parent_user_id' => 1, 'depth' => 1]);

        // C 登录（inviter = B）
        $this->postJson('/api/v1/auth/login', ['account' => 'leaf@test.com', 'password' => 'password123'])->assertOk();

        $this->assertDatabaseHas('referral_paths', ['user_id' => 3, 'parent_user_id' => 2, 'depth' => 2]);

        // 验证 C 的 path 包含 A 和 B
        $path = DB::table('referral_paths')->where('user_id', 3)->value('path');
        $this->assertStringContainsString('1', (string) $path);
        $this->assertStringContainsString('2', (string) $path);
    }

    // ─── helpers ───

    private function makeSub2User(
        int $id,
        string $username,
        string $email,
        ?string $affCode,
        ?int $inviterId,
        string $role = 'user',
        string $status = 'active',
    ): Sub2ApiUserData {
        return new Sub2ApiUserData(
            id: $id,
            email: $email,
            username: $username,
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            role: $role,
            status: $status,
            balance: '0',
            totalRecharged: '0',
            affCode: $affCode,
            inviterId: $inviterId,
            createdAt: CarbonImmutable::now(),
            updatedAt: CarbonImmutable::now(),
        );
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

        $this->app->instance(Sub2ApiUserRepository::class, new class ($byId, $byAccount) extends Sub2ApiUserRepository {
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
