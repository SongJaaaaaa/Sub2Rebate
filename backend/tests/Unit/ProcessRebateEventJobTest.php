<?php

namespace Tests\Unit;

use App\Jobs\ProcessRebateEventJob;
use App\Models\User;
use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Services\DecayRebateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessRebateEventJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_service_result_marks_event_failed(): void
    {
        User::query()->create([
            'id' => 1001,
            'username' => 'payer',
            'email' => 'payer@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
        $event = RebateEvent::query()->create([
            'user_id' => 1001,
            'source_type' => 'manual',
            'source_id' => 'unit-failed',
            'event_type' => 'recharge',
            'status' => RebateEvent::STATUS_PENDING,
            'source_amount' => '100',
            'source_currency' => 'CNY',
            'standard_amount' => '100',
            'standard_currency' => 'CNY',
            'credit_amount' => '100',
            'config_snapshot' => [],
        ]);

        $milestones = Mockery::mock(MilestoneService::class);
        $milestones->shouldReceive('process')->once()->andReturn([
            'ok' => false,
            'message' => '里程碑失败',
        ]);
        $decayRebates = Mockery::mock(DecayRebateService::class);
        $decayRebates->shouldNotReceive('process');

        try {
            (new ProcessRebateEventJob($event))->handle($milestones, $decayRebates);
            $this->fail('Job should throw when service returns failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('里程碑失败', $e->getMessage());
        }

        $this->assertDatabaseHas('rebate_events', [
            'id' => $event->id,
            'status' => RebateEvent::STATUS_FAILED,
            'error_message' => '里程碑失败',
        ]);
    }
}
