<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use App\Modules\Withdraw\Services\WithdrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 深度测试：提现流程
 *
 * 覆盖：
 * - 提现账号绑定（正常 / 参数缺失）
 * - 提现申请（正常 / 余额不足 / 低于最低额 / 每日次数限制）
 * - 提现审核（通过 / 拒绝 / 标记打款）
 * - 余额冻结与释放
 * - 状态流转幂等
 * - 提现配置 API
 */
class DeepWithdrawFlowTest extends TestCase
{
    use RefreshDatabase;

    // ─── 提现账号 ───

    public function test_save_withdraw_account_success(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(WithdrawService::class);

        $result = $service->saveAccount($user, [
            'type' => 'alipay',
            'realName' => '张三',
            'accountNo' => '138****1234',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('withdraw_accounts', [
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '张三',
            'account_no' => '138****1234',
        ]);
    }

    public function test_save_withdraw_account_rejects_empty_name(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(WithdrawService::class);

        $result = $service->saveAccount($user, [
            'type' => 'alipay',
            'realName' => '',
            'accountNo' => '138****1234',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['status']);
    }

    public function test_save_withdraw_account_rejects_empty_account_no(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(WithdrawService::class);

        $result = $service->saveAccount($user, [
            'type' => 'alipay',
            'realName' => '张三',
            'accountNo' => '',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function test_save_withdraw_account_updates_existing(): void
    {
        $user = $this->user(1001, 'user');
        $service = app(WithdrawService::class);

        $service->saveAccount($user, ['type' => 'alipay', 'realName' => '张三', 'accountNo' => '111']);
        $service->saveAccount($user, ['type' => 'wechat', 'realName' => '李四', 'accountNo' => '222']);

        $this->assertSame(1, WithdrawAccount::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('withdraw_accounts', [
            'user_id' => $user->id,
            'type' => 'wechat',
            'real_name' => '李四',
            'account_no' => '222',
        ]);
    }

    // ─── 提现申请 ───

    public function test_apply_withdraw_success(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);
        $service = app(WithdrawService::class);

        $result = $service->apply($user, ['amount' => '100']);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => $user->id,
            'amount' => '100.000000',
            'status' => WithdrawRecord::STATUS_PENDING,
        ]);
        // 余额应冻结
        $balance = RebateBalance::query()->where('user_id', $user->id)->first();
        $this->assertSame('100.000000', $this->money($balance->available_amount));
        $this->assertSame('100.000000', $this->money($balance->frozen_amount));
    }

    public function test_apply_withdraw_rejects_insufficient_balance(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 30.0);
        $this->setupWithdrawAccount($user);
        $service = app(WithdrawService::class);

        $result = $service->apply($user, ['amount' => '100']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('余额', $result['message']);
    }

    public function test_apply_withdraw_rejects_below_minimum(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);
        $service = app(WithdrawService::class);

        // 默认最低 50
        $result = $service->apply($user, ['amount' => '10']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('低于', $result['message']);
    }

    public function test_apply_withdraw_rejects_without_account(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        // 不设置提现账号
        $service = app(WithdrawService::class);

        $result = $service->apply($user, ['amount' => '100']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('账号', $result['message']);
    }

    public function test_apply_withdraw_daily_limit(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 500.0);
        $this->setupWithdrawAccount($user);
        $service = app(WithdrawService::class);

        // 默认 daily_limit = 1
        $first = $service->apply($user, ['amount' => '50']);
        $this->assertTrue($first['ok']);

        // 补回余额（模拟第二次申请前余额够）
        RebateBalance::query()->where('user_id', $user->id)->update([
            'available_amount' => '400.000000',
        ]);

        $second = $service->apply($user, ['amount' => '50']);
        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('次数', $second['message']);
    }

    // ─── 提现审核 ───

    public function test_admin_approve_withdraw_via_api(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);

        $service = app(WithdrawService::class);
        $result = $service->apply($user, ['amount' => '100']);
        $recordId = $result['record']['id'];

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/approve", ['remark' => '审核通过'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('withdraw_records', [
            'id' => $recordId,
            'status' => 'approved',
        ]);
    }

    public function test_admin_reject_withdraw_unfreezes_balance(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);

        $service = app(WithdrawService::class);
        $result = $service->apply($user, ['amount' => '100']);
        $recordId = $result['record']['id'];

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/reject", ['remark' => '拒绝'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // 余额应该解冻
        $balance = RebateBalance::query()->where('user_id', $user->id)->first();
        $this->assertSame('200.000000', $this->money($balance->available_amount));
        $this->assertSame('0.000000', $this->money($balance->frozen_amount));
    }

    public function test_admin_mark_paid_after_approve(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);

        $service = app(WithdrawService::class);
        $result = $service->apply($user, ['amount' => '100']);
        $recordId = $result['record']['id'];

        // 先审核通过
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/approve");

        // 再标记打款
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // 冻结应该转为已提现
        $balance = RebateBalance::query()->where('user_id', $user->id)->first();
        $this->assertSame('0.000000', $this->money($balance->frozen_amount));
        $this->assertSame('100.000000', $this->money($balance->withdrawn_amount));
    }

    public function test_cannot_approve_already_rejected_withdraw(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 200.0);
        $this->setupWithdrawAccount($user);

        $service = app(WithdrawService::class);
        $result = $service->apply($user, ['amount' => '100']);
        $recordId = $result['record']['id'];

        // 先拒绝
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/reject");

        // 再尝试通过
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson("/api/v1/admin/withdrawals/{$recordId}/approve")
            ->assertStatus(400);
    }

    // ─── 提现配置 API ───

    public function test_withdraw_config_api_returns_expected_fields(): void
    {
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/withdraw/config')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure(['data' => [
                'minAmount',
                'reviewMode',
                'dailyLimit',
                'tips',
            ]]);
    }

    public function test_withdraw_records_api_returns_paginated(): void
    {
        $user = $this->user(1001, 'user');
        $this->setupBalance($user, 500.0);
        $this->setupWithdrawAccount($user);

        $service = app(WithdrawService::class);
        $service->apply($user, ['amount' => '100']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/withdraw/records')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure(['data' => ['list', 'page', 'pageSize', 'total']]);
    }

    // ─── Helpers ───

    private function setupBalance(User $user, float $amount): void
    {
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => number_format($amount, 6, '.', ''),
            'frozen_amount' => '0.000000',
            'withdrawn_amount' => '0.000000',
        ]);
    }

    private function setupWithdrawAccount(User $user): void
    {
        WithdrawAccount::query()->create([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '测试用户',
            'account_no' => '138****0000',
        ]);
    }

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

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
