<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_recharge_100_gives_direct_parent_15(): void
    {
        [$parent, $child] = $this->parentAndChild();
        $event = $this->event($child, 'event-100', '100');

        $result = app(MilestoneService::class)->process($event);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['triggeredTimes']);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'payer_user_id' => $child->id,
            'receiver_user_id' => $parent->id,
            'type' => 'milestone',
            'rebate_amount' => '15',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $parent->id,
            'available_amount' => '15',
        ]);
        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $child->id,
            'total_recharge_amount' => '100',
            'milestone_times' => 1,
        ]);
        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_PENDING,
        ]);
    }

    public function test_two_recharges_100_each_give_30_total(): void
    {
        [$parent, $child] = $this->parentAndChild();
        $svc = app(MilestoneService::class);

        $svc->process($this->event($child, 'event-100-a', '100'));
        $svc->process($this->event($child, 'event-100-b', '100'));

        $this->assertSame(2, DB::table('rebate_records')->count());
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $parent->id,
            'available_amount' => '30',
        ]);
        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $child->id,
            'total_recharge_amount' => '200',
            'milestone_times' => 2,
        ]);
    }

    public function test_single_recharge_250_triggers_only_two_milestones(): void
    {
        [$parent, $child] = $this->parentAndChild();
        $event = $this->event($child, 'event-250', '250');

        $result = app(MilestoneService::class)->process($event);

        $this->assertSame(2, $result['triggeredTimes']);
        $this->assertSame(1, DB::table('rebate_records')->count());
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'rebate_amount' => '30',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $parent->id,
            'available_amount' => '30',
        ]);
        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $child->id,
            'total_recharge_amount' => '250',
            'milestone_times' => 2,
        ]);
    }

    public function test_reprocessing_same_event_does_not_duplicate_reward(): void
    {
        [$parent, $child] = $this->parentAndChild();
        $event = $this->event($child, 'event-repeat', '100');
        $svc = app(MilestoneService::class);

        $first = $svc->process($event);
        $event->refresh();
        $second = $svc->process($event);

        $this->assertTrue($first['processed']);
        $this->assertFalse($second['processed']);
        $this->assertSame(1, DB::table('rebate_records')->count());
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $parent->id,
            'available_amount' => '15',
        ]);
    }

    public function test_user_without_parent_updates_progress_but_no_reward(): void
    {
        $child = $this->user(1002, 'child');
        app(InviteService::class)->ensurePath($child);
        $event = $this->event($child, 'event-no-parent', '100');

        $result = app(MilestoneService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame(0, $result['triggeredTimes']);
        $this->assertSame(0, DB::table('rebate_records')->count());
        $this->assertSame(0, DB::table('rebate_balances')->count());
        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $child->id,
            'total_recharge_amount' => '100',
            'milestone_times' => 0,
        ]);
    }

    private function parentAndChild(): array
    {
        $parent = $this->user(1001, 'parent');
        $child = $this->user(1002, 'child');

        $invites = app(InviteService::class);
        $invites->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $invites->bind($child, $code);

        return [$parent, $child];
    }

    private function event(User $user, string $sourceId, string $amount): RebateEvent
    {
        $admin = $this->user(9001 + DB::table('users')->count(), 'admin'.DB::table('users')->count(), 'admin');
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
}
