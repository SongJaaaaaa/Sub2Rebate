<?php

namespace Tests\Feature;

use App\Jobs\ProcessRebateEventJob;
use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebateEventJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_pending_event_with_milestone_and_decay(): void
    {
        $parent = $this->user(1001, 'parent');
        $payer = $this->user(1002, 'payer');
        $this->bind($parent, $payer);
        $event = $this->event($payer, 'job-250', '250');

        ProcessRebateEventJob::dispatchSync($event);

        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_PROCESSED,
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'type' => 'milestone',
            'rebate_amount' => '30',
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'type' => 'decay',
            'rebate_amount' => '7.5',
        ]);
    }

    public function test_command_dispatches_pending_events(): void
    {
        $parent = $this->user(1001, 'parent');
        $payer = $this->user(1002, 'payer');
        $this->bind($parent, $payer);
        $event = $this->event($payer, 'cmd-100', '100');

        $this->artisan('rebate:process-pending')
            ->expectsOutput('已派发 1 个返利事件')
            ->assertSuccessful();

        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_PROCESSED,
        ]);
    }

    private function bind(User $parent, User $child): void
    {
        $invites = app(InviteService::class);
        $invites->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $invites->bind($child, $code);
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
