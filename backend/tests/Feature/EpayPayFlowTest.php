<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpayPayFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_epay_order_records_balance_before_and_returns_pay_info(): void
    {
        config([
            'services.epay.gateway' => 'https://pay.test',
            'services.epay.pid' => '20001',
            'services.epay.key' => 'epayTestKey',
            'services.epay.type' => 'alipay',
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        app(ConfigService::class)->ensureDefaults();
        // 打开 Epay 通道开关
        DB::table('config_items')->where('key', 'payment.epay_enabled')->update(['value' => json_encode(true)]);
        app(ConfigService::class)->forget();

        $user = User::query()->create([
            'id' => 1001,
            'username' => 'payer',
            'email' => 'payer@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        Http::fake([
            // 下单时查 Sub2API 当前余额（历史余额）
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 200],
            ]),
            // Epay mapi.php 下单
            'https://pay.test/mapi.php' => Http::response([
                'code' => 0,
                'trade_no' => 'EPAY-TRADE-1',
                'pay_type' => 'qrcode',
                'pay_info' => 'https://qr.alipay.com/abc123',
            ]),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withToken($token)
            ->postJson('/api/v1/recharge/epay/pay', ['amount' => '100'])
            ->assertOk()
            ->assertJsonPath('data.payType', 'qrcode')
            ->assertJsonPath('data.payInfo', 'https://qr.alipay.com/abc123')
            ->assertJsonPath('data.order.channel', 'epay')
            ->assertJsonPath('data.order.status', 'pending')
            // 下单时拿到的历史余额已记录
            ->assertJsonPath('data.order.sub2BalanceBefore', '200.00');

        $orderNo = $resp->json('data.order.orderNo');

        $this->assertDatabaseHas('recharge_orders', [
            'order_no' => $orderNo,
            'channel' => 'epay',
            'status' => 'pending',
            'sub2_balance_before' => '200.000000',
        ]);
    }
}
