<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Services\AdminWithdrawService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_then_mark_paid(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $svc = app(AdminWithdrawService::class);

        $approve = $svc->approve($admin, $record, '审核通过');
        $this->assertTrue($approve['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);

        $paid = $svc->markPaid($admin, $record->refresh(), '已线下打款');
        $this->assertTrue($paid['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => '0',
            'withdrawn_amount' => '100',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'withdraw',
            'action' => 'withdraw.approve',
            'remark' => '审核通过',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'withdraw',
            'action' => 'withdraw.mark_paid',
            'remark' => '已线下打款',
        ]);
    }

    public function test_admin_can_reject_and_unfreeze_balance(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');

        $result = app(AdminWithdrawService::class)->reject($admin, $record, '资料不一致');

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_REJECTED,
            'reject_reason' => '资料不一致',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '150',
            'frozen_amount' => '0',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdraw.reject',
            'remark' => '资料不一致',
        ]);
    }

    public function test_admin_withdraw_actions_require_admin_and_remark(): void
    {
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $svc = app(AdminWithdrawService::class);

        $notAdmin = $svc->approve($user, $record, '尝试审核');
        $this->assertFalse($notAdmin['ok']);
        $this->assertSame(403, $notAdmin['status']);

        $admin = $this->user(9001, 'admin', 'admin');
        $missingRemark = $svc->approve($admin, $record, '');
        $this->assertFalse($missingRemark['ok']);
        $this->assertSame('后台敏感操作必须填写备注', $missingRemark['message']);
    }

    public function test_withdraw_status_cannot_skip_to_paid(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');

        $result = app(AdminWithdrawService::class)->markPaid($admin, $record, '直接打款');

        $this->assertFalse($result['ok']);
        $this->assertSame('只有已审核通过的提现可以标记打款', $result['message']);
    }

    public function test_reject_fails_when_balance_is_missing(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        RebateBalance::query()->where('user_id', $user->id)->delete();

        $result = app(AdminWithdrawService::class)->reject($admin, $record, '资料不一致');

        $this->assertFalse($result['ok']);
        $this->assertSame('返利余额不存在', $result['message']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PENDING,
        ]);
    }

    private function pendingWithdraw(User $user, string $amount): WithdrawRecord
    {
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => $amount,
            'withdrawn_amount' => '0',
        ]);

        $account = WithdrawAccount::query()->create([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '张三',
            'account_no' => 'demo@example.com',
        ]);

        return WithdrawRecord::query()->create([
            'user_id' => $user->id,
            'withdraw_account_id' => $account->id,
            'amount' => $amount,
            'status' => WithdrawRecord::STATUS_PENDING,
            'account_type' => 'alipay',
            'account_no' => 'demo@example.com',
            'real_name' => '张三',
            'remark' => '提现',
        ]);
    }

    private function user(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->firstOrCreate(
            ['id' => $id],
            [
                'username' => $username,
                'email' => $username.'@example.com',
                'role' => $role,
                'status' => 'active',
            ]
        );
    }
}
