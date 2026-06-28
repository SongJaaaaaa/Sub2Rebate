<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_items_api_returns_default_values_and_tips(): void
    {
        $token = $this->loginToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/config/items')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.values.milestone.amount', '100')
            ->assertJsonPath('data.values.milestone.max_times', 2)
            ->assertJsonPath('data.values.rebate.stage_amount', '100')
            ->assertJsonPath('data.values.rebate.stage_reward_amount', '15')
            ->assertJsonPath('data.values.rebate.max_depth', 5)
            ->assertJsonPath('data.values.payment.cny_to_credit_rate', '1')
            ->assertJsonPath('data.values.withdraw.daily_limit', 1)
            ->assertJsonPath('data.values.withdraw.freeze_days', 0)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'key',
                            'group',
                            'name',
                            'type',
                            'value',
                            'tips',
                        ],
                    ],
                    'values',
                ],
            ]);

        $this->assertDatabaseHas('config_items', [
            'key' => 'withdraw.daily_limit',
            'group' => 'withdraw',
            'type' => 'int',
        ]);
    }

    public function test_config_service_get_reads_seeded_value(): void
    {
        $configs = app(ConfigService::class);

        $this->assertSame('50', $configs->get('withdraw.min_amount'));
        $this->assertSame(true, $configs->get('risk.blacklist_enabled'));
        $this->assertSame('fallback', $configs->get('missing.key', 'fallback'));
    }

    public function test_config_items_requires_login(): void
    {
        $this->getJson('/api/v1/config/items')
            ->assertUnauthorized()
            ->assertJson([
                'code' => 40101,
                'message' => '未登录',
                'data' => null,
            ]);
    }

    private function loginToken(): string
    {
        $user = User::query()->create([
            'id' => 2001,
            'username' => 'config-user',
            'email' => 'config@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        return $user->createToken('test')->plainTextToken;
    }
}
