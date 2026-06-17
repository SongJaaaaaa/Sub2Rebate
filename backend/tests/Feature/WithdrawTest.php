<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rebate\Models\RebateBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdraw_config_returns_default_rules(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->getJson('/api/v1/withdraw/config')
            ->assertOk()
            ->assertJsonPath('data.minAmount', '50.00')
            ->assertJsonPath('data.reviewMode', 'manual')
            ->assertJsonPath('data.dailyLimit', 1)
            ->assertJsonPath('data.freezeDays', 0);
    }

    public function test_user_can_save_and_read_withdraw_account(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/account', [
                'type' => 'alipay',
                'realName' => '张三',
                'accountNo' => 'demo@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.account.type', 'alipay')
            ->assertJsonPath('data.account.realName', '张三')
            ->assertJsonPath('data.account.accountNo', 'demo@example.com');

        $this->actingAs($user)
            ->getJson('/api/v1/withdraw/account')
            ->assertOk()
            ->assertJsonPath('data.account.realName', '张三');
    }

    public function test_apply_withdraw_freezes_balance_and_creates_pending_record(): void
    {
        $user = $this->user();
        $this->account($user);
        $this->balance($user, '200');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', [
                'amount' => '100',
                'remark' => '提现到支付宝',
            ])
            ->assertOk()
            ->assertJsonPath('data.record.amount', '100.00')
            ->assertJsonPath('data.record.status', 'pending')
            ->assertJsonPath('data.balance.availableAmount', '100.00')
            ->assertJsonPath('data.balance.frozenAmount', '100.00');

        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '100',
            'frozen_amount' => '100',
        ]);
        $this->assertDatabaseHas('withdraw_records', [
            'user_id' => $user->id,
            'amount' => '100',
            'status' => 'pending',
            'account_no' => 'demo@example.com',
            'real_name' => '张三',
        ]);
    }

    public function test_apply_withdraw_rejects_low_amount_insufficient_balance_and_daily_limit(): void
    {
        $user = $this->user();
        $this->account($user);
        $this->balance($user, '200');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '20'])
            ->assertBadRequest()
            ->assertJsonPath('message', '提现金额不能低于 50.00 元');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '100'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '60'])
            ->assertBadRequest()
            ->assertJsonPath('message', '今日提现次数已达上限');
    }

    public function test_apply_requires_account_and_enough_balance(): void
    {
        $user = $this->user();
        $this->balance($user, '40');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '60'])
            ->assertBadRequest()
            ->assertJsonPath('message', '请先绑定提现账号');

        $this->account($user);

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '60'])
            ->assertBadRequest()
            ->assertJsonPath('message', '可提现余额不足');
    }

    public function test_withdraw_records_are_paginated(): void
    {
        $user = $this->user();
        $this->account($user);
        $this->balance($user, '200');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '100'])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/withdraw/records?page=1&pageSize=20&status=pending')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.status', 'pending')
            ->assertJsonPath('data.list.0.amount', '100.00');
    }

    private function user(): User
    {
        return User::query()->create([
            'id' => 1001 + DB::table('users')->count(),
            'username' => 'user'.DB::table('users')->count(),
            'email' => 'user'.DB::table('users')->count().'@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    private function account(User $user): void
    {
        DB::table('withdraw_accounts')->insert([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '张三',
            'account_no' => 'demo@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function balance(User $user, string $available): void
    {
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => $available,
            'frozen_amount' => '0',
            'withdrawn_amount' => '0',
        ]);
    }
}
