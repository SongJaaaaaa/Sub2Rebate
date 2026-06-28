<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\PaymentRecord;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\UserRebateProgress;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_require_admin_user(): void
    {
        $user = $this->user(1001, 'normal');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 40301)
            ->assertJsonPath('message', '需要管理员权限');
    }

    public function test_admin_can_update_config_and_write_audit_log(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/rebate-config', [
                'values' => [
                    'rebate' => [
                        'stage_amount' => '100',
                        'stage_reward_amount' => '20',
                        'max_depth' => '6',
                        'recharge_bonus_100' => '8',
                        'recharge_bonus_200' => '18',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.values.rebate.stage_amount', '100')
            ->assertJsonPath('data.values.rebate.stage_reward_amount', '20')
            ->assertJsonPath('data.values.rebate.max_depth', '6')
            ->assertJsonPath('data.values.rebate.recharge_bonus_100', '8')
            ->assertJsonPath('data.values.rebate.recharge_bonus_200', '18');

        $this->assertDatabaseHas('config_items', [
            'key' => 'rebate.stage_reward_amount',
            'value' => json_encode('20'),
        ]);
        $this->assertDatabaseHas('config_items', [
            'key' => 'rebate.recharge_bonus_100',
            'value' => json_encode('8'),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'module' => 'config',
            'action' => 'config.update',
        ]);
    }

    public function test_admin_config_rejects_invalid_business_values(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/rebate-config', [
                'values' => [
                    'rebate' => [
                        'max_depth' => '99',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', '最大返利深度不能大于 20');
    }

    public function test_admin_can_adjust_user_balance(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->fakeSub2Users([
            $this->sub2User($admin, 'admin-pass'),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/balance-adjust', [
                'userId' => $user->id,
                'amount' => '25',
                'type' => 'add',
                'adminPassword' => 'admin-pass',
                'remark' => '补发',
            ])
            ->assertOk()
            ->assertJsonPath('data.availableAmount', '25.00');

        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '25',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'balance',
            'action' => 'balance.adjust',
            'target_user_id' => $user->id,
            'remark' => '补发',
        ]);

        $this->withToken($admin->createToken('test-2')->plainTextToken)
            ->postJson('/api/v1/admin/balance-adjust', [
                'userId' => $user->id,
                'amount' => '5',
                'type' => 'subtract',
                'adminPassword' => 'admin-pass',
                'remark' => '扣减',
            ])
            ->assertOk()
            ->assertJsonPath('data.availableAmount', '20.00');
    }

    public function test_admin_can_adjust_sub2api_quota_and_create_recharge_event(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1001,
                    'balance' => 125,
                    'total_recharged' => 125,
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->fakeSub2Users([
            $this->sub2User($admin, 'admin-pass'),
            $this->sub2User($user, 'user-pass'),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/users/'.$user->id.'/api-quota', [
                'amount' => '25',
                'type' => 'add',
                'reason' => '充值',
                'remark' => '余额充值',
            ])
            ->assertOk()
            ->assertJsonPath('data.userId', $user->id)
            ->assertJsonPath('data.reason', '充值')
            ->assertJsonPath('data.remark', '余额充值')
            ->assertJsonPath('data.amount', '25.00');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
                && $request->hasHeader('x-api-key', 'secret-key')
                && $request->hasHeader('Idempotency-Key')
                && (float) ($data['balance'] ?? 0) === 25.0
                && ($data['operation'] ?? '') === 'add';
        });

        $this->assertDatabaseHas('payment_records', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.admin_api_quota',
            'standard_amount' => '25',
            'remark' => '余额充值',
        ]);
        $this->assertDatabaseHas('rebate_events', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.admin_api_quota',
            'standard_amount' => '25',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'sub2api',
            'action' => 'sub2api.api_quota_adjust',
            'target_user_id' => $user->id,
            'remark' => '余额充值',
        ]);
    }

    public function test_admin_api_quota_recharge_syncs_lagged_milestone_progress(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1002/balance' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1002,
                    'balance' => 300,
                    'total_recharged' => 300,
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        $invites = app(InviteService::class);
        $invites->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $invites->bind($child, $code);
        $this->fakeSub2Users([
            $this->sub2User($admin, 'admin-pass'),
            $this->sub2User($parent, 'parent-pass'),
            new Sub2ApiUserData(
                id: (int) $child->id,
                email: (string) $child->email,
                username: (string) $child->username,
                passwordHash: Hash::make('child-pass'),
                role: 'user',
                status: 'active',
                balance: '299',
                totalRecharged: '299',
                affCode: 'AFF'.$child->id,
                inviterId: (int) $parent->id,
                createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
                updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            ),
        ]);

        PaymentRecord::query()->create([
            'user_id' => $child->id,
            'source_type' => 'seed',
            'source_id' => 'seed-child-299',
            'status' => 'paid',
            'source_amount' => '299',
            'standard_amount' => '299',
            'credit_amount' => '299',
            'config_snapshot' => [],
        ]);
        UserRebateProgress::query()->create([
            'user_id' => $child->id,
            'total_recharge_amount' => '0',
            'milestone_times' => 0,
            'last_event_id' => null,
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/users/'.$child->id.'/api-quota', [
                'amount' => '1',
                'type' => 'add',
                'reason' => '充值',
                'remark' => '余额充值',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '1.00');

        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $child->id,
            'total_recharge_amount' => '300',
            'milestone_times' => 2,
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'payer_user_id' => $child->id,
            'receiver_user_id' => $parent->id,
            'type' => 'decay',
            'rebate_amount' => '15',
        ]);
        $this->assertDatabaseMissing('rebate_records', [
            'payer_user_id' => $child->id,
            'receiver_user_id' => $parent->id,
            'type' => 'milestone',
        ]);
    }

    public function test_admin_can_view_sub2api_quota_snapshot(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->fakeSub2Users([
            $this->sub2User($admin, 'admin-pass'),
            new Sub2ApiUserData(
                id: (int) $user->id,
                email: (string) $user->email,
                username: (string) $user->username,
                passwordHash: Hash::make('user-pass'),
                role: 'user',
                status: 'active',
                balance: '128.50',
                totalRecharged: '200',
                affCode: 'AFF1001',
                inviterId: null,
                createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
                updatedAt: CarbonImmutable::parse('2026-06-14 10:00:00', 'Asia/Shanghai'),
            ),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users/'.$user->id.'/api-quota')
            ->assertOk()
            ->assertJsonPath('data.userId', $user->id)
            ->assertJsonPath('data.apiBalance', '128.50')
            ->assertJsonPath('data.totalUsed', '71.50')
            ->assertJsonPath('data.totalCharged', '200.00')
            ->assertJsonPath('data.sub2ApiAffCode', 'AFF1001');
    }

    public function test_admin_quota_snapshot_prefers_local_paid_total_when_sub2api_total_is_zero(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        PaymentRecord::query()->create([
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.admin_api_quota',
            'source_id' => 'quota-total-1001',
            'status' => 'paid',
            'source_amount' => '25',
            'source_currency' => 'CNY',
            'standard_amount' => '25',
            'standard_currency' => 'CNY',
            'credit_amount' => '25',
            'config_snapshot' => [],
            'remark' => 'API 额度充值',
            'paid_at' => now(),
        ]);
        $this->fakeSub2Users([
            $this->sub2User($admin, 'admin-pass'),
            new Sub2ApiUserData(
                id: (int) $user->id,
                email: (string) $user->email,
                username: (string) $user->username,
                passwordHash: Hash::make('user-pass'),
                role: 'user',
                status: 'active',
                balance: '25',
                totalRecharged: '0',
                affCode: 'AFF1001',
                inviterId: null,
                createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
                updatedAt: CarbonImmutable::parse('2026-06-14 10:00:00', 'Asia/Shanghai'),
            ),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users/'.$user->id.'/api-quota')
            ->assertOk()
            ->assertJsonPath('data.apiBalance', '25.00')
            ->assertJsonPath('data.totalUsed', '0.00')
            ->assertJsonPath('data.totalCharged', '25.00');
    }

    public function test_admin_quota_snapshot_falls_back_to_sub2api_admin_api(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1001,
                    'email' => 'user@example.com',
                    'username' => 'api-user',
                    'balance' => 80,
                    'total_recharged' => 100,
                    'updated_at' => '2026-06-14T10:00:00+08:00',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->app->instance(Sub2ApiUserRepository::class, new class extends Sub2ApiUserRepository {
            public function findById(int $id): ?Sub2ApiUserData
            {
                throw new RuntimeException('tunnel closed');
            }
        });

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users/'.$user->id.'/api-quota')
            ->assertOk()
            ->assertJsonPath('data.username', 'api-user')
            ->assertJsonPath('data.apiBalance', '80.00')
            ->assertJsonPath('data.totalUsed', '20.00')
            ->assertJsonPath('data.totalCharged', '100.00');
    }

    public function test_admin_can_view_sub2api_quota_adjust_records(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');

        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'balance',
            'action' => 'balance.adjust',
            'after_values' => ['delta' => '88'],
            'remark' => '返利余额调整',
        ]);
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'sub2api',
            'action' => 'sub2api.api_quota_adjust',
            'after_values' => [
                'amount' => '25.000000',
                'operation' => 'add',
                'reason' => '充值',
                'rebate_event_id' => 123,
            ],
            'remark' => '余额充值',
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users/'.$user->id.'/api-quota-records')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.type', 'add')
            ->assertJsonPath('data.list.0.amount', '25.00')
            ->assertJsonPath('data.list.0.reason', '充值')
            ->assertJsonPath('data.list.0.remark', '余额充值')
            ->assertJsonPath('data.list.0.rebateEventId', 123);
    }

    public function test_admin_can_adjust_sub2api_quota_with_admin_jwt_fallback(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => '',
            'sub2rebate.sub2api_admin_email' => 'admin@example.com',
            'sub2rebate.sub2api_admin_password' => 'pass',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'code' => 0,
                'data' => [
                    'access_token' => 'jwt-token',
                ],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 1001,
                    'balance' => 25,
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/users/'.$user->id.'/api-quota', [
                'amount' => '25',
                'type' => 'add',
                'reason' => '充值',
                'remark' => '余额充值',
            ])
            ->assertOk()
            ->assertJsonPath('data.userId', $user->id)
            ->assertJsonPath('data.amount', '25.00');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/auth/login'
            && $request['email'] === 'admin@example.com'
            && $request['password'] === 'pass');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
            && $request->hasHeader('Authorization', 'Bearer jwt-token'));
    }

    public function test_admin_can_manage_users(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/users?keyword=user')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/ban', ['remark' => '测试封禁'])
            ->assertOk()
            ->assertJsonPath('data.status', 'banned');

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/'.$user->id.'/role', ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_ban_user_revokes_existing_tokens(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/admin/users/'.$user->id.'/ban', ['remark' => '测试封禁'])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()
            ->withToken($userToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_admin_withdraw_controller_lists_and_approves_records(): void
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
            'account_no' => 'demo@example.com',
            'real_name' => '测试用户',
        ]);
        $record = WithdrawRecord::query()->create([
            'user_id' => $user->id,
            'withdraw_account_id' => $account->id,
            'amount' => '100',
            'status' => WithdrawRecord::STATUS_PENDING,
            'account_type' => $account->type,
            'account_no' => $account->account_no,
            'real_name' => $account->real_name,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/withdrawals?status=pending')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.id', $record->id);

        $this->withToken($token)
            ->postJson('/api/v1/admin/withdrawals/'.$record->id.'/approve', ['remark' => '通过'])
            ->assertOk()
            ->assertJsonPath('data.status', WithdrawRecord::STATUS_APPROVED);
    }

    public function test_admin_relationship_audit_and_rebate_override_routes(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');
        PaymentRecord::query()->create([
            'user_id' => $parent->id,
            'source_type' => 'manual',
            'source_id' => 'relationship-test-parent',
            'status' => 'paid',
            'source_amount' => '88',
            'standard_amount' => '88',
            'credit_amount' => '88',
            'config_snapshot' => [],
        ]);
        $invites = app(InviteService::class);
        $invites->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $invites->bind($child, $code);
        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'target_user_id' => $child->id,
            'module' => 'user',
            'action' => 'user.test',
            'remark' => '测试日志',
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/relationship-tree?userId='.$parent->id)
            ->assertOk()
            ->assertJsonPath('data.root.id', $parent->id)
            ->assertJsonPath('data.root.nickname', 'parent')
            ->assertJsonPath('data.root.level', 'Top Master')
            ->assertJsonPath('data.root.totalRecharge', '88.00')
            ->assertJsonPath('data.root.directReferrals', 1)
            ->assertJsonPath('data.root.status', 'active')
            ->assertJsonPath('data.root.children.0.id', $child->id)
            ->assertJsonPath('data.root.children.0.level', 'Referral L1');

        $this->withToken($token)
            ->getJson('/api/v1/admin/audit-logs?actionType=user.test')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.remark', '测试日志');

        $this->withToken($token)
            ->putJson('/api/v1/admin/users/'.$child->id.'/rebate-override', [
                'enabled' => true,
                'customRates' => [
                    ['level' => 1, 'rate' => '0.10'],
                    ['level' => 2, 'rate' => '0.05'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.userId', $child->id)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.customRates.0.rate', '0.10');

        $this->assertDatabaseHas('config_items', [
            'key' => 'rebate.user_override.'.$child->id,
            'group' => 'rebate',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/user-rebate-overrides')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.userId', $child->id);
    }

    public function test_admin_dashboard_returns_core_stats(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '10',
            'frozen_amount' => '5',
            'withdrawn_amount' => '1',
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.totalUsers', 2)
            ->assertJsonPath('data.rebateBalanceAmount', '15.00');
    }

    private function user(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function sub2User(User $user, string $password): Sub2ApiUserData
    {
        return new Sub2ApiUserData(
            id: (int) $user->id,
            email: (string) $user->email,
            username: (string) $user->username,
            passwordHash: Hash::make($password),
            role: (string) $user->role,
            status: (string) $user->status,
            balance: '0',
            totalRecharged: '0',
            affCode: 'AFF'.$user->id,
            inviterId: $user->sub2api_inviter_id,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );
    }

    /**
     * @param array<int, Sub2ApiUserData> $users
     */
    private function fakeSub2Users(array $users): void
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
