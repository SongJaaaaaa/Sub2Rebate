<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Rebate\Services\DecayRebateCalculator;
use App\Modules\Rebate\Services\DecayRebateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 深度测试：返佣计算逻辑
 *
 * 覆盖：
 * - DecayRebateCalculator 单元测试（权重分配、精度、边界）
 * - 多层衰减比例验证
 * - 里程碑 + 衰减联动
 * - 大金额精度不丢失
 * - 0 金额 / 负金额边界
 * - 无上级不产生记录
 * - 重复处理幂等
 * - 用户自定义返利覆盖
 */
class DeepRebateCalculationTest extends TestCase
{
    use RefreshDatabase;

    // ─── DecayRebateCalculator 单元 ───

    public function test_calculator_single_receiver_gets_full_pool(): void
    {
        $calc = new DecayRebateCalculator();
        $result = $calc->calculate(15.0, [1001], 0.4);

        $this->assertCount(1, $result);
        $this->assertSame(15.0, $result[0]['amount']);
        $this->assertSame(1001, $result[0]['user_id']);
        $this->assertSame(1, $result[0]['level']);
    }

    public function test_calculator_two_receivers_decay_0_4(): void
    {
        $calc = new DecayRebateCalculator();
        $result = $calc->calculate(14.0, [1001, 1002], 0.4);

        // weights: 1.0, 0.4 → total = 1.4
        // level1: 14 * 1.0/1.4 = 10.0, level2: 14 * 0.4/1.4 = 4.0
        $this->assertCount(2, $result);
        $this->assertSame(10.0, $result[0]['amount']);
        $this->assertSame(4.0, $result[1]['amount']);
    }

    public function test_calculator_three_receivers_sum_equals_pool(): void
    {
        $calc = new DecayRebateCalculator();
        $pool = 15.0;
        $result = $calc->calculate($pool, [1001, 1002, 1003], 0.4);

        $sum = array_sum(array_column($result, 'amount'));
        $this->assertSame(number_format($pool, 6, '.', ''), number_format($sum, 6, '.', ''));
    }

    public function test_calculator_empty_receivers_returns_empty(): void
    {
        $calc = new DecayRebateCalculator();
        $this->assertSame([], $calc->calculate(100.0, [], 0.4));
    }

    public function test_calculator_zero_pool_returns_empty(): void
    {
        $calc = new DecayRebateCalculator();
        $this->assertSame([], $calc->calculate(0.0, [1001, 1002], 0.4));
    }

    public function test_calculator_negative_pool_returns_empty(): void
    {
        $calc = new DecayRebateCalculator();
        $this->assertSame([], $calc->calculate(-10.0, [1001], 0.4));
    }

    public function test_calculator_five_level_chain_descending_amounts(): void
    {
        $calc = new DecayRebateCalculator();
        $result = $calc->calculate(100.0, [1, 2, 3, 4, 5], 0.4);

        $this->assertCount(5, $result);
        for ($i = 1; $i < count($result); $i++) {
            $this->assertGreaterThan($result[$i]['amount'], $result[$i - 1]['amount']);
        }
    }

    public function test_calculator_high_precision_large_amount(): void
    {
        $calc = new DecayRebateCalculator();
        $pool = 999999.999999;
        $result = $calc->calculate($pool, [1, 2, 3], 0.4);

        $sum = array_sum(array_column($result, 'amount'));
        // 确保精度误差不超过 0.000001
        $this->assertLessThan(0.000002, abs($pool - $sum));
    }

    // ─── DecayRebateService 完整流程 ───

    public function test_decay_rebate_not_triggered_for_non_recharge_event(): void
    {
        $user = $this->user(1001, 'payer');
        $event = RebateEvent::query()->create([
            'user_id' => $user->id,
            'source_type' => 'manual',
            'source_id' => 'test-nonrecharge',
            'event_type' => 'refund', // 非充值
            'status' => RebateEvent::STATUS_PENDING,
            'source_amount' => '100',
            'source_currency' => 'CNY',
            'standard_amount' => '100',
            'standard_currency' => 'CNY',
            'credit_amount' => '100',
            'config_snapshot' => [],
        ]);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertFalse($result['processed']);
        $this->assertStringContainsString('充值', $result['message']);
    }

    public function test_decay_rebate_no_records_when_payer_has_no_ancestors(): void
    {
        $payer = $this->user(1001, 'orphan');
        $event = $this->createEvent($payer, 'orphan-100', '100');
        $this->progress($payer, '300', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame([], $result['records']);
        $this->assertSame(0, DB::table('rebate_records')->where('type', 'decay')->count());
    }

    public function test_decay_rebate_idempotent_reprocessing(): void
    {
        [$parent, $payer] = $this->pairWithBinding();
        $event = $this->createEvent($payer, 'idempotent-1', '100');
        $this->progress($payer, '300', 2, $event->id);

        $service = app(DecayRebateService::class);
        $first = $service->process($event);
        $second = $service->process($event);

        $this->assertTrue($first['processed']);
        $this->assertFalse($second['processed']);
        $this->assertStringContainsString('已处理', $second['message']);
        $this->assertSame(1, DB::table('rebate_records')->where('event_id', $event->id)->where('type', 'decay')->count());
    }

    public function test_decay_rebate_amount_increases_balance(): void
    {
        [$parent, $payer] = $this->pairWithBinding();
        $event = $this->createEvent($payer, 'balance-1', '100');
        $this->progress($payer, '300', 2, $event->id);

        app(DecayRebateService::class)->process($event);

        $balance = DB::table('rebate_balances')->where('user_id', $parent->id)->first();
        $this->assertNotNull($balance);
        $this->assertGreaterThan(0, (float) $balance->available_amount);
    }

    // ─── 里程碑 + 衰减联动 ───

    public function test_milestone_then_decay_combined_for_300_recharge(): void
    {
        [$parent, $payer] = $this->pairWithBinding();
        $event = $this->createEvent($payer, 'combo-300', '300');

        // 里程碑处理
        $milestoneResult = app(MilestoneService::class)->process($event);
        $this->assertTrue($milestoneResult['ok']);
        $this->assertTrue($milestoneResult['processed']);

        // 衰减处理
        $event->refresh();
        $decayResult = app(DecayRebateService::class)->process($event);
        $this->assertTrue($decayResult['ok']);

        // 里程碑记录
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'type' => 'milestone',
        ]);
        $this->assertDatabaseHas('rebate_records', [
            'event_id' => $event->id,
            'receiver_user_id' => $parent->id,
            'type' => 'decay',
            'rebate_amount' => '15',
        ]);
    }

    public function test_milestone_skipped_when_threshold_not_reached(): void
    {
        [$parent, $payer] = $this->pairWithBinding();
        $event = $this->createEvent($payer, 'sub-threshold', '50');

        $result = app(MilestoneService::class)->process($event);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['processed']);
        // 未达里程碑阈值 100，不会有里程碑记录
        $this->assertSame(0, $result['triggeredTimes']);
    }

    public function test_milestone_max_times_cap(): void
    {
        [$parent, $payer] = $this->pairWithBinding();

        // 连续 3 次 100 充值，但 max_times=2
        for ($i = 1; $i <= 3; $i++) {
            $event = $this->createEvent($payer, "cap-$i", '100');
            app(MilestoneService::class)->process($event);
        }

        $milestoneRecords = DB::table('rebate_records')
            ->where('payer_user_id', $payer->id)
            ->where('type', 'milestone')
            ->count();

        // 最多触发 2 次里程碑（受 max_times=2 限制）
        $this->assertLessThanOrEqual(2, $milestoneRecords);
    }

    public function test_custom_rates_sum_matches_rounded_pool(): void
    {
        $top = $this->user(1001, 'top');
        $mid = $this->user(1002, 'mid');
        $parent = $this->user(1003, 'parent');
        $payer = $this->user(1004, 'payer');
        $this->bind($top, $mid);
        $this->bind($mid, $parent);
        $this->bind($parent, $payer);

        DB::table('config_items')->insert([
            'key' => 'rebate.user_override.'.$payer->id,
            'group' => 'rebate',
            'name' => '测试自定义费率',
            'type' => 'json',
            'value' => json_encode([
                'enabled' => true,
                'customRates' => [
                    ['level' => 1, 'rate' => '0.3333333'],
                    ['level' => 2, 'rate' => '0.3333333'],
                    ['level' => 3, 'rate' => '0.3333333'],
                ],
            ]),
            'tips' => '测试自定义费率',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = $this->createEvent($payer, 'custom-rates-rounding', '100.000001');
        $this->progress($payer, '300.000001', 2, $event->id);

        $result = app(DecayRebateService::class)->process($event);

        $this->assertTrue($result['processed']);
        $this->assertSame('14.999985', $result['poolAmount']);
        $sum = RebateRecord::query()
            ->where('event_id', $event->id)
            ->where('type', 'decay')
            ->sum('rebate_amount');
        $this->assertSame('14.999985', number_format((float) $sum, 6, '.', ''));
    }

    // ─── helpers ───

    private function pairWithBinding(): array
    {
        $parent = $this->user(1001, 'parent');
        $payer = $this->user(1002, 'payer');
        $this->bind($parent, $payer);
        return [$parent, $payer];
    }

    private function bind(User $parent, User $child): void
    {
        $service = app(InviteService::class);
        $service->ensurePath($parent);
        $code = (string) DB::table('referral_paths')->where('user_id', $parent->id)->value('invite_code');
        $service->bind($child, $code);
    }

    private function createEvent(User $payer, string $sourceId, string $amount): RebateEvent
    {
        Queue::fake();
        $admin = $this->user(9001 + DB::table('users')->count(), 'admin' . DB::table('users')->count(), 'admin');
        $result = app(RechargeEventService::class)->createManual($admin, $payer, [
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
                'email' => $username . '@example.com',
                'role' => $role,
                'status' => 'active',
            ]
        );
    }
}
