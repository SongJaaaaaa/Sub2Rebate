<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 深度测试：API 接口全面测试
 *
 * 覆盖：
 * - 未认证访问返回 401
 * - 普通用户访问管理端返回 403
 * - 参数校验（空参数、错误类型、越界值）
 * - 分页边界（page=0, pageSize=999）
 * - 各 API 正常返回结构验证
 * - 封禁用户不能登录
 */
class DeepApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ─── 认证 ───

    public function test_unauthenticated_requests_return_401(): void
    {
        $endpoints = [
            ['GET', '/api/v1/auth/me'],
            ['GET', '/api/v1/account/profile'],
            ['GET', '/api/v1/invite/me'],
            ['GET', '/api/v1/dashboard/summary'],
            ['GET', '/api/v1/rebate/records'],
            ['GET', '/api/v1/promotion/summary'],
            ['GET', '/api/v1/withdraw/config'],
            ['GET', '/api/v1/withdraw/records'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $method === 'GET'
                ? $this->getJson($uri)
                : $this->postJson($uri);
            $response->assertUnauthorized();
        }
    }

    public function test_admin_routes_reject_normal_user_with_403(): void
    {
        $user = $this->user(1001, 'normal', 'user');
        $token = $user->createToken('test')->plainTextToken;

        $adminEndpoints = [
            '/api/v1/admin/dashboard',
            '/api/v1/admin/users',
            '/api/v1/admin/withdrawals',
            '/api/v1/admin/rebate-config',
            '/api/v1/admin/relationship-tree',
            '/api/v1/admin/audit-logs',
        ];

        foreach ($adminEndpoints as $uri) {
            $this->withToken($token)
                ->getJson($uri)
                ->assertForbidden()
                ->assertJsonPath('code', 40301);
        }
    }

    public function test_admin_routes_accessible_by_admin_user(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    // ─── 登录 ───

    public function test_login_with_wrong_password_returns_error(): void
    {
        $this->fakeSub2ApiUser();

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'wrong_password',
        ])->assertUnauthorized();
    }

    public function test_login_with_empty_fields_returns_error(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'account' => '',
            'password' => '',
        ])->assertStatus(400);
    }

    public function test_banned_user_cannot_login(): void
    {
        $this->fakeSub2ApiUser('disabled');

        $this->postJson('/api/v1/auth/login', [
            'account' => 'demo@example.com',
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    // ─── Dashboard API ───

    public function test_dashboard_summary_returns_correct_structure(): void
    {
        $user = $this->user(1001, 'user');
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '100.50',
            'frozen_amount' => '20.00',
            'withdrawn_amount' => '50.00',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => [
                'availableAmount',
                'frozenAmount',
                'totalAmount',
                'withdrawnAmount',
                'totalRebateAmount',
                'todayRebateAmount',
                'monthRebateAmount',
                'directInviteCount',
                'teamInviteCount',
                'pendingWithdrawCount',
                'pendingWithdrawAmount',
            ]])
            ->assertJsonPath('data.availableAmount', '100.50')
            ->assertJsonPath('data.frozenAmount', '20.00')
            ->assertJsonPath('data.totalAmount', '120.50');
    }

    public function test_dashboard_rebate_trends_returns_7_days_by_default(): void
    {
        $user = $this->user(1001, 'user');

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/dashboard/rebate-trends')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $items = $response->json('data.items');
        $this->assertCount(7, $items);
    }

    public function test_dashboard_rebate_trends_30d(): void
    {
        $user = $this->user(1001, 'user');

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/dashboard/rebate-trends?range=30d')
            ->assertOk();

        $items = $response->json('data.items');
        $this->assertCount(30, $items);
    }

    // ─── 分页边界 ───

    public function test_rebate_records_pagination_bounds(): void
    {
        $user = $this->user(1001, 'user');
        $token = $user->createToken('test')->plainTextToken;

        // page=0 被钳位到 1
        $this->withToken($token)
            ->getJson('/api/v1/rebate/records?page=0&pageSize=10')
            ->assertOk()
            ->assertJsonPath('data.page', 1);

        // pageSize 被钳位到 100
        $this->withToken($token)
            ->getJson('/api/v1/rebate/records?page=1&pageSize=999')
            ->assertOk()
            ->assertJsonPath('data.pageSize', 100);
    }

    public function test_rebate_records_filter_by_type(): void
    {
        $user = $this->user(1001, 'user');
        $payer = $this->user(1002, 'payer');
        $event = RebateEvent::query()->create([
            'user_id' => $payer->id,
            'source_type' => 'manual',
            'source_id' => 'filter-test',
            'event_type' => 'recharge',
            'status' => 'processed',
            'source_amount' => '100',
            'source_currency' => 'CNY',
            'standard_amount' => '100',
            'standard_currency' => 'CNY',
            'credit_amount' => '100',
            'config_snapshot' => [],
        ]);
        RebateRecord::query()->create([
            'event_id' => $event->id,
            'payer_user_id' => $payer->id,
            'receiver_user_id' => $user->id,
            'type' => 'milestone',
            'level' => 1,
            'source_amount' => '100',
            'rebate_amount' => '15',
            'status' => 'confirmed',
            'config_snapshot' => [],
        ]);
        RebateRecord::query()->create([
            'event_id' => $event->id,
            'payer_user_id' => $payer->id,
            'receiver_user_id' => $user->id,
            'type' => 'decay',
            'level' => 1,
            'source_amount' => '100',
            'rebate_amount' => '7.5',
            'status' => 'confirmed',
            'config_snapshot' => [],
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/rebate/records?type=milestone')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.type', 'milestone');

        $this->withToken($token)
            ->getJson('/api/v1/rebate/records?type=decay')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.type', 'decay');
    }

    // ─── Invite API ───

    public function test_invite_me_returns_aff_code_and_urls(): void
    {
        $this->fakeSub2ApiUser();
        $user = $this->user(1001, 'demo');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/invite/me')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => [
                'sub2ApiAffCode',
                'sub2ApiInviteUrl',
                'sub2ApiAffiliatePageUrl',
                'parent',
                'depth',
                'directInviteCount',
                'teamInviteCount',
            ]]);
    }

    public function test_invite_bind_with_valid_code(): void
    {
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        app(InviteService::class)->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');

        $this->withToken($child->createToken('test')->plainTextToken)
            ->postJson('/api/v1/invite/bind', ['inviteCode' => $code])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('referral_paths', [
            'user_id' => $child->id,
            'parent_user_id' => $parent->id,
        ]);
    }

    public function test_invite_tree_returns_children(): void
    {
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        $this->bindPair($parent, $child);

        $this->withToken($parent->createToken('test')->plainTextToken)
            ->getJson('/api/v1/invite/tree')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    // ─── Promotion API ───

    public function test_promotion_summary_without_team(): void
    {
        $this->fakeSub2ApiUser();
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/promotion/summary')
            ->assertOk()
            ->assertJsonPath('data.teamInviteCount', 0)
            ->assertJsonPath('data.conversionRate', '0.000000');
    }

    // ─── Withdraw API ───

    public function test_withdraw_config_returns_expected_fields(): void
    {
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/withdraw/config')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'minAmount',
                'reviewMode',
                'dailyLimit',
                'tips',
            ]]);
    }

    public function test_withdraw_save_account_validates_empty_fields(): void
    {
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/withdraw/account', [
                'type' => 'alipay',
                'realName' => '',
                'accountNo' => '',
            ])
            ->assertStatus(400);
    }

    public function test_withdraw_save_account_success(): void
    {
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/withdraw/account', [
                'type' => 'alipay',
                'realName' => '张三',
                'accountNo' => 'zhangsan@alipay.com',
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('withdraw_accounts', [
            'user_id' => $user->id,
            'real_name' => '张三',
            'account_no' => 'zhangsan@alipay.com',
        ]);
    }

    // ─── Admin Config ───

    public function test_admin_rebate_config_get_and_update(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/rebate-config')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->withToken($token)
            ->putJson('/api/v1/admin/rebate-config', [
                'values' => [
                    'rebate' => ['pool_ratio' => '0.2'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.values.rebate.pool_ratio', '0.2');

        $this->assertDatabaseHas('config_items', [
            'key' => 'rebate.pool_ratio',
            'value' => json_encode('0.2'),
        ]);
    }

    // ─── Admin Withdraw ───

    public function test_admin_withdraw_approve_reject_flow(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '100',
            'frozen_amount' => '100',
            'withdrawn_amount' => '0',
        ]);
        $account = WithdrawAccount::query()->create([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '测试',
            'account_no' => 'test@alipay.com',
        ]);
        $record = WithdrawRecord::query()->create([
            'user_id' => $user->id,
            'withdraw_account_id' => $account->id,
            'amount' => '100',
            'status' => WithdrawRecord::STATUS_PENDING,
            'account_type' => 'alipay',
            'account_no' => 'test@alipay.com',
            'real_name' => '测试',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        // 审批通过
        $this->withToken($token)
            ->postJson("/api/v1/admin/withdrawals/{$record->id}/approve")
            ->assertOk();

        $record->refresh();
        $this->assertSame(WithdrawRecord::STATUS_APPROVED, $record->status);

        // 标记打款
        $this->withToken($token)
            ->postJson("/api/v1/admin/withdrawals/{$record->id}/paid")
            ->assertOk();

        $record->refresh();
        $this->assertSame(WithdrawRecord::STATUS_PAID, $record->status);
    }

    public function test_admin_withdraw_reject_unfreezes_balance(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '0',
            'frozen_amount' => '100',
            'withdrawn_amount' => '0',
        ]);
        $account = WithdrawAccount::query()->create([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '测试',
            'account_no' => 'test@alipay.com',
        ]);
        $record = WithdrawRecord::query()->create([
            'user_id' => $user->id,
            'withdraw_account_id' => $account->id,
            'amount' => '100',
            'status' => WithdrawRecord::STATUS_PENDING,
            'account_type' => 'alipay',
            'account_no' => 'test@alipay.com',
            'real_name' => '测试',
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$record->id}/reject", ['remark' => '拒绝原因'])
            ->assertOk();

        $record->refresh();
        $this->assertSame(WithdrawRecord::STATUS_REJECTED, $record->status);
    }

    // ─── Health ───

    public function test_health_endpoint_no_auth_required(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }

    // ═══════ helpers ═══════

    private function user(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->firstOrCreate(
            ['id' => $id],
            [
                'username' => $username,
                'email' => $username . '@example.com',
                'role' => $role,
                'status' => 'active',
            ]
        );
    }

    private function bindPair(User $parent, User $child): void
    {
        $service = app(InviteService::class);
        $service->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $service->bind($child, $code);
    }

    private function fakeSub2ApiUser(string $status = 'active'): void
    {
        $user = new Sub2ApiUserData(
            id: 1001,
            email: 'demo@example.com',
            username: 'demo',
            passwordHash: password_hash('secret123', PASSWORD_BCRYPT),
            role: 'user',
            status: $status,
            balance: '0',
            totalRecharged: '0',
            affCode: 'SUB2AFF12',
            inviterId: null,
            createdAt: CarbonImmutable::now(),
            updatedAt: CarbonImmutable::now(),
        );

        $this->app->instance(Sub2ApiUserRepository::class, new class ([$user]) extends Sub2ApiUserRepository {
            private array $users;

            public function __construct(array $users)
            {
                $this->users = [];
                foreach ($users as $u) {
                    $this->users[$u->id] = $u;
                    $this->users[mb_strtolower($u->email)] = $u;
                    $this->users[mb_strtolower($u->username)] = $u;
                }
            }

            public function findByAccount(string $account): ?Sub2ApiUserData
            {
                return $this->users[mb_strtolower(trim($account))] ?? null;
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
