<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Services\DecayRebateService;
use App\Modules\Risk\Models\RiskFlag;
use App\Modules\Risk\Services\RiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdraw_apply_writes_audit_log(): void
    {
        $user = $this->user(1001, 'user');
        $this->account($user);
        $this->balance($user, '200');

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '100', 'remark' => '提现审计'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'target_user_id' => $user->id,
            'module' => 'withdraw',
            'action' => 'withdraw.apply',
            'remark' => '提现审计',
        ]);
    }

    public function test_blacklist_user_cannot_withdraw(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->account($user);
        $this->balance($user, '200');

        app(RiskService::class)->flagUser($user, RiskFlag::TYPE_BLACKLIST, '异常提现', $admin);

        $this->actingAs($user)
            ->postJson('/api/v1/withdraw/apply', ['amount' => '100'])
            ->assertBadRequest()
            ->assertJsonPath('message', '当前账号不可提现');

        $this->assertSame(0, DB::table('withdraw_records')->count());
        $this->assertDatabaseHas('risk_flags', [
            'user_id' => $user->id,
            'type' => RiskFlag::TYPE_BLACKLIST,
            'status' => RiskFlag::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
    }

    public function test_milestone_and_decay_rebates_write_audit_logs(): void
    {
        $parent = $this->user(1001, 'parent');
        $payer = $this->user(1002, 'payer');
        $this->bind($parent, $payer);

        $event = $this->event($payer, 'audit-rebate-300', '300');
        app(MilestoneService::class)->process($event);
        app(DecayRebateService::class)->process($event->refresh());

        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $parent->id,
            'module' => 'rebate',
            'action' => 'rebate.milestone_granted',
            'remark' => '里程碑奖励发放',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $parent->id,
            'module' => 'rebate',
            'action' => 'rebate.decay_granted',
            'remark' => '多级返利分配发放',
        ]);
    }

    private function bind(User $parent, User $child): void
    {
        $invites = app(InviteService::class);
        $invites->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $invites->bind($child, $code);
    }

    private function event(User $user, string $sourceId, string $amount): object
    {
        $admin = $this->user(9002 + DB::table('users')->count(), 'admin'.DB::table('users')->count(), 'admin');
        $result = app(RechargeEventService::class)->createManual($admin, $user, [
            'source_type' => 'manual_admin',
            'source_id' => $sourceId,
            'source_amount' => $amount,
            'remark' => '测试充值',
        ]);

        return $result['rebateEvent'];
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
