<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Rebate\Services\DecayRebateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DecayRebateTest extends TestCase
{
    use RefreshDatabase;

    public function test_decay_rebate_splits_pool_across_three_levels(): void
    {
        [$a, $b, $c, $payer] = $this->chain();
        $event = $this->event($payer, 'decay-001', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame('100.000000', $result['normalAmount']);
        $this->assertSame('15.000000', $result['poolAmount']);

        $rows = DB::table('rebate_records')
            ->where('event_id', $event->id)
            ->where('type', 'decay')
            ->orderBy('level')
            ->get();

        $this->assertSame(3, $rows->count());
        $this->assertSame($c->id, (int) $rows[0]->receiver_user_id);
        $this->assertSame($b->id, (int) $rows[1]->receiver_user_id);
        $this->assertSame($a->id, (int) $rows[2]->receiver_user_id);

        $amounts = $rows->map(fn ($row): float => (float) $row->rebate_amount)->all();
        $this->assertSame('15.000000', $this->money(array_sum($amounts)));
        $this->assertGreaterThan($amounts[1], $amounts[0]);
        $this->assertGreaterThan($amounts[2], $amounts[1]);
        $this->assertSame('9.615384', $this->money($amounts[0]));
        $this->assertSame('3.846154', $this->money($amounts[1]));
        $this->assertSame('1.538462', $this->money($amounts[2]));

        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $c->id,
            'available_amount' => '9.615384',
        ]);
        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_PENDING,
        ]);
    }

    public function test_decay_rebate_is_skipped_before_milestone_finished(): void
    {
        [, , , $payer] = $this->chain();
        $event = $this->event($payer, 'decay-skip', '100');
        $this->progress($payer, '100', 1, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertFalse($result['processed']);
        $this->assertSame('0.000000', $result['normalAmount']);
        $this->assertSame(0, DB::table('rebate_records')->where('type', 'decay')->count());
    }

    public function test_single_recharge_250_pays_milestone_and_decay_for_remaining_amount(): void
    {
        $parent = $this->user(1001, 'parent');
        $payer = $this->user(1002, 'payer');
        $this->bind($parent, $payer);

        $event = $this->event($payer, 'decay-250', '250');
        app(MilestoneService::class)->process($event);
        $result = app(DecayRebateService::class)->process($event->refresh());

        $this->assertTrue($result['processed']);
        $this->assertSame('50.000000', $result['normalAmount']);
        $this->assertSame('7.500000', $result['poolAmount']);
        $this->assertSame(2, DB::table('rebate_records')->count());
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
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $parent->id,
            'available_amount' => '37.5',
        ]);
    }

    public function test_decay_rebate_is_idempotent(): void
    {
        [, , $parent, $payer] = $this->chain();
        $event = $this->event($payer, 'decay-repeat', '100');
        $this->progress($payer, '300', 2, $event->id);
        $svc = app(DecayRebateService::class);

        $first = $svc->process($event);
        $second = $svc->process($event->refresh());

        $this->assertTrue($first['processed']);
        $this->assertFalse($second['processed']);
        $this->assertSame(3, DB::table('rebate_records')->where('type', 'decay')->count());
        $this->assertSame('9.615384', $this->money(DB::table('rebate_balances')->where('user_id', $parent->id)->value('available_amount')));
    }

    public function test_custom_rebate_override_rates_are_used_for_payer(): void
    {
        [$a, $b, $c, $payer] = $this->chain();
        DB::table('config_items')->insert([
            'key' => 'rebate.user_override.'.$payer->id,
            'group' => 'rebate',
            'name' => '用户返利层级 '.$payer->id,
            'type' => 'json',
            'value' => json_encode([
                'enabled' => true,
                'customRates' => [
                    ['level' => 1, 'rate' => '0.20'],
                    ['level' => 2, 'rate' => '0.10'],
                    ['level' => 3, 'rate' => '0.05'],
                ],
            ]),
            'tips' => '测试',
            'sort' => 900,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = $this->event($payer, 'decay-custom', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame('35.000000', $result['poolAmount']);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $c->id,
            'level' => 1,
            'rebate_amount' => '20',
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $b->id,
            'level' => 2,
            'rebate_amount' => '10',
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $a->id,
            'level' => 3,
            'rebate_amount' => '5',
        ]);
    }

    public function test_decay_rebate_without_parent_marks_event_but_creates_no_records(): void
    {
        $payer = $this->user(1009, 'payer');
        app(InviteService::class)->ensurePath($payer);
        $event = $this->event($payer, 'decay-no-parent', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame('没有上级', $result['message']);
        $this->assertSame(0, DB::table('rebate_records')->where('type', 'decay')->count());
        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_PENDING,
            'error_message' => '没有上级，未发放衰减返利',
        ]);
    }

    private function chain(): array
    {
        $a = $this->user(1001, 'a');
        $b = $this->user(1002, 'b');
        $c = $this->user(1003, 'c');
        $payer = $this->user(1004, 'payer');

        $this->bind($a, $b);
        $this->bind($b, $c);
        $this->bind($c, $payer);

        return [$a, $b, $c, $payer];
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

    private function progress(User $user, string $totalAmount, int $times, int $eventId): void
    {
        DB::table('user_rebate_progress')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'total_recharge_amount' => $totalAmount,
                'milestone_times' => $times,
                'last_event_id' => $eventId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
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

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
