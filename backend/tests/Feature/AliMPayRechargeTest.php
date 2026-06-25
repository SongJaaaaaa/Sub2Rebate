<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AliMPayRechargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_alimpay_order_with_submit_url(): void
    {
        $user = $this->user(1001, 'user');
        $this->setAliMPayConfig();

        $res = $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recharge/orders', [
                'amount' => '100',
            ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'alimpay_qr')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.outTradeNo', fn ($value) => is_string($value) && str_starts_with($value, 'RC'))
            ->assertJsonPath('data.payUrl', fn ($value) => is_string($value) && str_starts_with($value, 'https://pay.example.com/submit.php?'));

        $payUrl = (string) $res->json('data.payUrl');
        parse_str((string) parse_url($payUrl, PHP_URL_QUERY), $params);

        $this->assertSame('1001000000000001', $params['pid']);
        $this->assertSame('alipay', $params['type']);
        $this->assertSame('100.00', $params['money']);
        $this->assertSame('https://rebate.example.com/api/v1/payments/alimpay/notify', $params['notify_url']);
        $this->assertSame('MD5', $params['sign_type']);
        $this->assertSame($this->sign($params, 'merchant-secret'), $params['sign']);
    }

    public function test_alimpay_notify_credits_order_and_writes_logs(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 105],
            ]),
        ]);

        $user = $this->user(1001, 'user');
        $this->setAliMPayConfig();
        $order = $this->createAliMPayOrder($user, '100.000000', '105.000000');

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

        $this->get('/api/v1/payments/alimpay/notify?'.http_build_query($payload))
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
        ]);
        $this->assertDatabaseHas('payment_records', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.recharge_order',
            'source_id' => 'recharge-order-'.$order->order_no,
            'standard_amount' => '100',
            'credit_amount' => '105',
        ]);
        $this->assertDatabaseHas('payment_notify_logs', [
            'provider' => 'alimpay',
            'out_trade_no' => $order->out_trade_no,
            'verify_passed' => true,
            'handle_status' => 'processed',
        ]);
    }

    public function test_alimpay_notify_rejects_bad_signature(): void
    {
        $user = $this->user(1001, 'user');
        $this->setAliMPayConfig();
        $order = $this->createAliMPayOrder($user, '100.000000', '105.000000');

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

        $this->get('/api/v1/payments/alimpay/notify?'.http_build_query($payload))
            ->assertBadRequest()
            ->assertSeeText('fail');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('payment_notify_logs', [
            'provider' => 'alimpay',
            'out_trade_no' => $order->out_trade_no,
            'verify_passed' => false,
            'handle_status' => 'failed',
        ]);
    }

    private function createAliMPayOrder(User $user, string $amount, string $credit): RechargeOrder
    {
        return RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606240001',
            'channel' => RechargeOrder::CHANNEL_ALIMPAY_QR,
            'out_trade_no' => 'RC202606240001',
            'subject' => 'API充值-100.00元',
            'amount' => $amount,
            'bonus_amount' => '5.000000',
            'credit_amount' => $credit,
            'status' => RechargeOrder::STATUS_PENDING,
            'credit_status' => RechargeOrder::CREDIT_PENDING,
            'pay_url' => 'https://pay.example.com/submit.php',
            'expire_at' => now()->addMinutes(15),
        ]);
    }

    private function setAliMPayConfig(): void
    {
        app(ConfigService::class)->ensureDefaults();

        $items = [
            'payment.mode' => 'alimpay_qr',
            'payment.qr_enabled' => true,
            'payment.order_expire_minutes' => 15,
            'payment.alimpay.enabled' => true,
            'payment.alimpay.pid' => '1001000000000001',
            'payment.alimpay.key' => 'merchant-secret',
            'payment.alimpay.gateway_url' => 'https://pay.example.com',
            'payment.alimpay.notify_url' => 'https://rebate.example.com/api/v1/payments/alimpay/notify',
            'payment.alimpay.return_url' => 'https://rebate.example.com/recharge',
            'payment.alimpay.display_name' => 'AliMPay/经营码',
            'payment.alimpay.sitename' => 'Sub2Rebate',
        ];

        foreach ($items as $key => $value) {
            DB::table('config_items')->where('key', $key)->update(['value' => json_encode($value)]);
        }

        app(ConfigService::class)->forget();
    }

    private function sign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, fn ($value): bool => $value !== '' && $value !== null);
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
