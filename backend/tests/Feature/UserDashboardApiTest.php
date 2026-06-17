<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Rebate\Models\RebateRecord;
use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dashboard_and_rebate_records_return_real_data(): void
    {
        $payer = $this->user(1001, 'payer');
        $receiver = $this->user(1002, 'receiver');
        $token = $receiver->createToken('test')->plainTextToken;
        RebateBalance::query()->create([
            'user_id' => $receiver->id,
            'available_amount' => '12',
            'frozen_amount' => '3',
            'withdrawn_amount' => '2',
        ]);
        $event = RebateEvent::query()->create([
            'user_id' => $payer->id,
            'source_type' => 'manual',
            'source_id' => 'dash-1',
            'event_type' => 'recharge',
            'status' => RebateEvent::STATUS_PROCESSED,
            'source_amount' => '100',
            'source_currency' => 'CNY',
            'standard_amount' => '100',
            'standard_currency' => 'CNY',
            'credit_amount' => '100',
            'config_snapshot' => [],
        ]);
        RebateRecord::query()->create([
            'event_id' => $event->id,
            'payer_user_id' => $payer->id,
            'receiver_user_id' => $receiver->id,
            'type' => RebateRecord::TYPE_DECAY,
            'level' => 1,
            'source_amount' => '100',
            'rebate_amount' => '15',
            'status' => 'confirmed',
            'config_snapshot' => [],
            'remark' => '测试返利',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.totalAmount', '15.00')
            ->assertJsonPath('data.totalRebateAmount', '15.00');

        $this->withToken($token)
            ->getJson('/api/v1/rebate/records')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.rebateAmount', '15.00');
    }

    public function test_promotion_summary_returns_zero_conversion_rate_without_team(): void
    {
        $this->fakeSub2Users();
        $user = $this->user(1001, 'user');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/promotion/summary')
            ->assertOk()
            ->assertJsonPath('data.teamInviteCount', 0)
            ->assertJsonPath('data.totalPaidUserCount', 0)
            ->assertJsonPath('data.conversionRate', '0.000000');
    }

    private function fakeSub2Users(): void
    {
        $this->app->instance(Sub2ApiUserRepository::class, new class extends Sub2ApiUserRepository {
            public function findByAccount(string $account): ?Sub2ApiUserData
            {
                return null;
            }

            public function findById(int $id): ?Sub2ApiUserData
            {
                return null;
            }
        });
    }

    private function user(int $id, string $username): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }
}
