<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Rebate\Services\DecayRebateService;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RebateInactiveNodeTest extends TestCase
{
    use RefreshDatabase;

    public array $fakeSub2Users = [];

    public function test_disabled_node_stays_in_tree_and_cannot_receive_milestone(): void
    {
        [$a, $b, $c] = $this->chain(3);
        $b->forceFill([
            'rebate_status' => 'disabled',
            'rebate_disabled_reason' => 'manual',
        ])->save();

        $event = $this->event($c, 'inactive-milestone', '100');
        $result = app(MilestoneService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame(0, $result['triggeredTimes']);
        $this->assertDatabaseMissing('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $b->id,
            'type' => 'milestone',
        ]);
        $this->assertDatabaseHas('user_rebate_progress', [
            'user_id' => $c->id,
            'milestone_times' => 1,
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/relationship-tree?userId='.$a->id)
            ->assertOk()
            ->assertJsonPath('data.root.children.0.id', $b->id)
            ->assertJsonPath('data.root.children.0.rebateStatus', 'disabled')
            ->assertJsonPath('data.root.children.0.children.0.id', $c->id);

        $this->actingAs($b)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_platform_mode_keeps_original_levels_and_sends_disabled_share_to_platform(): void
    {
        [$a, $b, $c, $payer] = $this->chain(4);
        $b->forceFill(['rebate_status' => 'disabled'])->save();
        $this->setConfig('rebate.inactive_node_mode', 'platform');

        $event = $this->event($payer, 'inactive-platform', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame('15.000000', $result['poolAmount']);

        $rows = DB::table('rebate_records')
            ->where('event_id', $event->id)
            ->where('type', 'decay')
            ->orderBy('level')
            ->get();

        $this->assertSame([$c->id, $a->id], $rows->pluck('receiver_user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame([1, 3], $rows->pluck('level')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('11.153846', $this->money($rows->sum(fn ($row): float => (float) $row->rebate_amount)));
        $this->assertDatabaseMissing('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $b->id,
        ]);
    }

    public function test_exclude_recalculate_mode_skips_disabled_node_and_renumbers_levels(): void
    {
        [$a, $b, $c, $payer] = $this->chain(4);
        $b->forceFill(['rebate_status' => 'disabled'])->save();
        $this->setConfig('rebate.inactive_node_mode', 'exclude_recalculate');

        $event = $this->event($payer, 'inactive-recalc', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $rows = DB::table('rebate_records')
            ->where('event_id', $event->id)
            ->where('type', 'decay')
            ->orderBy('level')
            ->get();

        $this->assertSame([$c->id, $a->id], $rows->pluck('receiver_user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame([1, 2], $rows->pluck('level')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('15.000000', $this->money($rows->sum(fn ($row): float => (float) $row->rebate_amount)));
        $this->assertSame('10.714286', $this->money($rows[0]->rebate_amount));
        $this->assertSame('4.285714', $this->money($rows[1]->rebate_amount));
    }

    public function test_lie_flat_command_disables_inactive_user_and_successful_recharge_at_threshold_restores_it(): void
    {
        $user = $this->user(1001, 'lazy');
        $user->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
            'last_sub2api_balance' => '100',
            'last_sub2api_total_recharged' => '100',
            'last_balance_checked_at' => now()->subDays(1),
        ])->save();

        $this->fakeSub2Users([
            $this->sub2User($user, '100', '100'),
        ]);

        $this->artisan('rebate:check-lie-flat-users --limit=10')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'rebate_status' => 'disabled',
            'rebate_disabled_reason' => 'lie_flat',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'target_user_id' => $user->id,
            'action' => 'rebate.eligibility_disabled',
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        app(RechargeEventService::class)->createManual($admin, $user->refresh(), [
            'source_type' => 'manual_admin',
            'source_id' => 'restore-recharge',
            'source_amount' => '10',
            'remark' => '恢复测试',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'rebate_status' => 'eligible',
            'rebate_disabled_reason' => null,
        ]);
    }

    public function test_sub2api_total_recharged_growth_records_activity_but_does_not_restore_rebate_status(): void
    {
        $user = $this->user(1001, 'lazy');
        $user->forceFill([
            'rebate_status' => 'disabled',
            'rebate_disabled_reason' => 'lie_flat',
            'last_sub2api_balance' => '100',
            'last_sub2api_total_recharged' => '100',
        ])->save();

        $this->fakeSub2Users([$this->sub2User($user, '120.01', '120.01')]);
        $this->artisan('rebate:check-lie-flat-users --limit=10')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'rebate_status' => 'disabled',
            'rebate_disabled_reason' => 'lie_flat',
        ]);
        $this->assertNotNull($user->refresh()->last_recharge_at);
    }

    public function test_balance_decrease_keeps_user_active_and_balance_increase_does_not_create_event(): void
    {
        $user = $this->user(1001, 'active');
        $user->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
            'last_sub2api_balance' => '100',
            'last_sub2api_total_recharged' => '100',
        ])->save();
        $this->fakeSub2Users([$this->sub2User($user, '80', '100')]);

        $this->artisan('rebate:check-lie-flat-users --limit=10')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'rebate_status' => 'eligible',
        ]);
        $this->assertNotNull($user->refresh()->last_balance_decreased_at);
        $this->assertSame(0, DB::table('rebate_events')->count());

        $this->fakeSub2Users([$this->sub2User($user, '120', '100')]);
        $this->artisan('rebate:check-lie-flat-users --limit=10')->assertSuccessful();

        $this->assertSame(0, DB::table('rebate_events')->count());
    }

    private function chain(int $count): array
    {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $users[] = $this->user(1000 + $i, 'u'.$i);
        }

        for ($i = 1; $i < $count; $i++) {
            $this->bind($users[$i - 1], $users[$i]);
        }

        return $users;
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
        $admin = $this->user(8000 + DB::table('users')->count(), 'admin'.DB::table('users')->count(), 'admin');
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

    private function setConfig(string $key, mixed $value): void
    {
        app(ConfigService::class)->ensureDefaults();
        DB::table('config_items')->where('key', $key)->update([
            'value' => json_encode($value),
            'updated_at' => now(),
        ]);
        app(ConfigService::class)->forget();
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

    private function sub2User(User $user, string $balance, string $total): Sub2ApiUserData
    {
        return new Sub2ApiUserData(
            id: (int) $user->id,
            email: (string) $user->email,
            username: (string) $user->username,
            passwordHash: Hash::make('secret123'),
            role: (string) $user->role,
            status: (string) $user->status,
            balance: $balance,
            totalRecharged: $total,
            affCode: 'AFF'.$user->id,
            inviterId: null,
            createdAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
            updatedAt: CarbonImmutable::parse('2026-06-13 12:00:00', 'Asia/Shanghai'),
        );
    }

    private function fakeSub2Users(array $users): void
    {
        $this->fakeSub2Users = [];
        foreach ($users as $user) {
            $this->fakeSub2Users[$user->id] = $user;
        }

        $this->app->instance(Sub2ApiUserRepository::class, new class($this) extends Sub2ApiUserRepository {
            public function __construct(private readonly RebateInactiveNodeTest $test)
            {
            }

            public function findById(int $id): ?Sub2ApiUserData
            {
                return $this->test->fakeSub2Users[$id] ?? null;
            }
        });
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
