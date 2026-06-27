<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpayRechargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_epay_order_with_mapi_pay_url(): void
    {
        Http::fake([
            'https://pay.example.com/mapi.php' => Http::response([
                'code' => 1,
                'msg' => 'succ',
                'trade_no' => '20260624123000123456',
                'payurl' => 'https://pay.example.com/pay/submit/20260624123000123456/',
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setEpayConfig();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recharge/orders', [
                'amount' => '100',
            ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'epay')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.providerTradeNo', '20260624123000123456')
            ->assertJsonPath('data.outTradeNo', fn ($value) => is_string($value) && str_starts_with($value, 'RC'))
            ->assertJsonPath('data.payUrl', 'https://pay.example.com/pay/submit/20260624123000123456/');

        Http::assertSent(function ($request): bool {
            $params = $request->data();

            return $request->url() === 'https://pay.example.com/mapi.php'
                && $params['pid'] === '1001000000000001'
                && $params['type'] === 'alipay'
                && $params['money'] === '100.00'
                && is_numeric($params['timestamp'] ?? null)
                && $params['notify_url'] === 'https://rebate.example.com/api/v1/recharge/epay/notify'
                && $params['sign_type'] === 'MD5'
                && $params['sign'] === $this->sign($params, 'merchant-secret');
        });
    }

    public function test_epay_notify_credits_order_and_writes_logs(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::sequence()
                ->push(['code' => 0, 'data' => ['id' => 1001, 'balance' => 20]])
                ->push(['code' => 0, 'data' => ['id' => 1001, 'balance' => 125]]),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 125],
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setEpayConfig();
        $order = $this->createEpayOrder($user, '100.000000', '105.000000');

        $payload = [
            'pid' => '1001000000000001',
            'trade_no' => '20260624123000123456',
            'out_trade_no' => $order->out_trade_no,
            'type' => 'alipay',
            'name' => $order->subject,
            'money' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];
        $payload['sign'] = $this->sign($payload, 'merchant-secret');
        $payload['sign_type'] = 'MD5';

        $this->get('/api/v1/recharge/epay/notify?'.http_build_query($payload))
            ->assertOk()
            ->assertSeeText('success');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
            && (float) ($request->data()['balance'] ?? 0) === 105.0
            && ($request->data()['operation'] ?? '') === 'add'
            && $request->hasHeader('x-api-key', 'secret-key'));

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_APPROVED,
            'credit_status' => RechargeOrder::CREDIT_SUCCESS,
            'provider_trade_no' => '20260624123000123456',
            'paid_amount' => '100',
            'sub2_balance_before' => '20',
            'sub2_balance_after' => '125',
        ]);
        $this->assertDatabaseHas('payment_records', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.recharge_order',
            'source_id' => 'recharge-order-'.$order->order_no,
            'standard_amount' => '100',
            'credit_amount' => '105',
        ]);
        $this->assertDatabaseHas('payment_notify_logs', [
            'provider' => 'epay',
            'out_trade_no' => $order->out_trade_no,
            'verify_passed' => true,
            'handle_status' => 'processed',
        ]);
    }

    public function test_epay_return_sync_credits_current_user_order(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 20],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 125],
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setEpayConfig();
        $order = $this->createEpayOrder($user, '100.000000', '105.000000');

        $payload = [
            'pid' => '1001000000000001',
            'trade_no' => '20260624123000123456',
            'out_trade_no' => $order->out_trade_no,
            'type' => 'alipay',
            'name' => $order->subject,
            'money' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];
        $payload['sign'] = $this->sign($payload, 'merchant-secret');
        $payload['sign_type'] = 'MD5';

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recharge/epay/return', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', RechargeOrder::STATUS_APPROVED)
            ->assertJsonPath('data.sub2BalanceBefore', '20.00')
            ->assertJsonPath('data.sub2BalanceAfter', '125.00');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_APPROVED,
            'credit_status' => RechargeOrder::CREDIT_SUCCESS,
            'paid_amount' => '100',
            'sub2_balance_before' => '20',
            'sub2_balance_after' => '125',
        ]);
    }

    public function test_epay_notify_rejects_bad_signature(): void
    {
        $user = $this->user(1001, 'user');
        $this->setEpayConfig();
        $order = $this->createEpayOrder($user, '100.000000', '105.000000');

        $payload = [
            'pid' => '1001000000000001',
            'trade_no' => '20260624123000123456',
            'out_trade_no' => $order->out_trade_no,
            'type' => 'alipay',
            'name' => $order->subject,
            'money' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
            'sign' => 'bad-sign',
            'sign_type' => 'MD5',
        ];

        $this->get('/api/v1/recharge/epay/notify?'.http_build_query($payload))
            ->assertBadRequest()
            ->assertSeeText('fail');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('payment_notify_logs', [
            'provider' => 'epay',
            'out_trade_no' => $order->out_trade_no,
            'verify_passed' => false,
            'handle_status' => 'failed',
        ]);
    }

    public function test_user_can_create_epay_order_with_legacy_alimpay_config(): void
    {
        Http::fake([
            'https://pay.sjiaa.cc.cd/mapi.php' => Http::response([
                'code' => 1,
                'msg' => 'succ',
                'trade_no' => '20260627153000123456',
                'payurl' => 'https://pay.sjiaa.cc.cd/pay/submit/20260627153000123456/',
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setLegacyAlimpayConfig();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recharge/orders', [
                'amount' => '50',
            ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'epay')
            ->assertJsonPath('data.payUrl', 'https://pay.sjiaa.cc.cd/pay/submit/20260627153000123456/');
    }

    public function test_legacy_alimpay_notify_route_is_still_supported(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::sequence()
                ->push(['code' => 0, 'data' => ['id' => 1001, 'balance' => 20]])
                ->push(['code' => 0, 'data' => ['id' => 1001, 'balance' => 125]]),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 125],
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setLegacyAlimpayConfig();
        $order = $this->createEpayOrder($user, '100.000000', '105.000000');

        $payload = [
            'pid' => '1001',
            'trade_no' => '20260624123000123456',
            'out_trade_no' => $order->out_trade_no,
            'type' => 'alipay',
            'name' => $order->subject,
            'money' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];
        $payload['sign'] = $this->sign($payload, 'legacy-secret');
        $payload['sign_type'] = 'MD5';

        $this->get('/api/v1/payments/alimpay/notify?'.http_build_query($payload))
            ->assertOk()
            ->assertSeeText('success');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_APPROVED,
            'credit_status' => RechargeOrder::CREDIT_SUCCESS,
        ]);
    }

    private function createEpayOrder(User $user, string $amount, string $credit): RechargeOrder
    {
        return RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606240001',
            'channel' => RechargeOrder::CHANNEL_EPAY,
            'out_trade_no' => 'RC202606240001',
            'subject' => 'API充值-100.00元',
            'amount' => $amount,
            'bonus_amount' => '5.000000',
            'credit_amount' => $credit,
            'status' => RechargeOrder::STATUS_PENDING,
            'credit_status' => RechargeOrder::CREDIT_PENDING,
            'pay_url' => 'https://pay.example.com/pay/submit/20260624123000123456/',
            'expire_at' => now()->addMinutes(15),
        ]);
    }

    private function setEpayConfig(): void
    {
        app(ConfigService::class)->ensureDefaults();

        $items = [
            'payment.mode' => 'epay',
            'payment.qr_enabled' => true,
            'payment.order_expire_minutes' => 15,
            'payment.epay.enabled' => true,
            'payment.epay.pid' => '1001000000000001',
            'payment.epay.key' => 'merchant-secret',
            'payment.epay.gateway_url' => 'https://pay.example.com',
            'payment.epay.notify_url' => 'https://rebate.example.com/api/v1/recharge/epay/notify',
            'payment.epay.return_url' => 'https://rebate.example.com/recharge',
            'payment.epay.display_name' => 'Epay 当面付',
            'payment.epay.sitename' => 'Sub2Rebate',
            'payment.epay.type' => 'alipay',
        ];

        foreach ($items as $key => $value) {
            DB::table('config_items')->where('key', $key)->update(['value' => json_encode($value)]);
        }

        app(ConfigService::class)->forget();
    }

    private function setLegacyAlimpayConfig(): void
    {
        app(ConfigService::class)->ensureDefaults();

        $items = [
            'payment.mode' => 'alimpay_qr',
            'payment.qr_enabled' => true,
            'payment.order_expire_minutes' => 15,
            'payment.alimpay.enabled' => true,
            'payment.alimpay.pid' => '1001',
            'payment.alimpay.key' => 'legacy-secret',
            'payment.alimpay.gateway_url' => 'https://pay.sjiaa.cc.cd',
            'payment.alimpay.notify_url' => 'https://rebate.example.com/api/v1/payments/alimpay/notify',
            'payment.alimpay.return_url' => 'https://rebate.example.com/recharge',
            'payment.alimpay.display_name' => '支付宝当面付',
            'payment.alimpay.sitename' => 'Sub2Rebate',
        ];

        foreach ($items as $key => $value) {
            DB::table('config_items')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'payment',
                    'name' => $key,
                    'type' => match ($key) {
                        'payment.alimpay.enabled' => 'bool',
                        default => 'string',
                    },
                    'value' => json_encode($value),
                    'tips' => '',
                    'sort' => 999,
                    'is_public' => true,
                ]
            );
        }

        app(ConfigService::class)->forget();
    }

    private function sign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, fn ($value): bool => $value !== '' && $value !== null && ! is_array($value));
        ksort($params);

        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = $name.'='.$value;
        }

        return md5(implode('&', $parts).$key);
    }

    private function user(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
