<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_payment_config(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/payment-config')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.channel', 'alipay')
            ->assertJsonPath('data.mode', 'manual_qr')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.expireMinutes', 15)
            ->assertJsonPath('data.creditRate', '1')
            ->assertJsonPath('data.alimpay.enabled', false)
            ->assertJsonPath('data.alimpay.key', '')
            ->assertJsonPath('data.alimpay.hasKey', false);
    }

    public function test_admin_can_update_payment_config(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'manual_qr',
                'qrUrl' => 'https://example.com/alipay-qr.png',
                'displayName' => '张三-支付宝收款码',
                'note' => '付款后请等待审核到账',
                'expireMinutes' => 20,
                'creditRate' => '1.5',
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'manual_qr')
            ->assertJsonPath('data.qrUrl', 'https://example.com/alipay-qr.png')
            ->assertJsonPath('data.displayName', '张三-支付宝收款码')
            ->assertJsonPath('data.note', '付款后请等待审核到账')
            ->assertJsonPath('data.expireMinutes', 20)
            ->assertJsonPath('data.creditRate', '1.5');

        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.alipay_qr_url',
            'value' => json_encode('https://example.com/alipay-qr.png'),
        ]);
        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.alipay_display_name',
            'value' => json_encode('张三-支付宝收款码'),
        ]);
        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.order_expire_minutes',
            'value' => json_encode(20),
        ]);
    }

    public function test_payment_config_requires_qr_when_enabled(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'manual_qr',
                'qrUrl' => '',
                'displayName' => '张三',
                'note' => 'test',
                'expireMinutes' => 15,
                'creditRate' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', '开启二维码充值时必须提供支付宝二维码');
    }

    public function test_admin_can_update_alimpay_config_without_key_echo(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'alimpay_qr',
                'qrUrl' => '',
                'displayName' => '',
                'note' => '手工二维码提示',
                'expireMinutes' => 10,
                'creditRate' => '1',
                'alimpay' => [
                    'enabled' => true,
                    'pid' => '1001000000000001',
                    'key' => 'merchant-secret',
                    'gatewayUrl' => 'https://pay.example.com',
                    'notifyUrl' => 'https://rebate.example.com/api/v1/payments/alimpay/notify',
                    'returnUrl' => 'https://rebate.example.com/recharge',
                    'displayName' => 'AliMPay/经营码',
                    'sitename' => 'Sub2Rebate',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'alimpay_qr')
            ->assertJsonPath('data.channel', 'alimpay_qr')
            ->assertJsonPath('data.alimpay.pid', '1001000000000001')
            ->assertJsonPath('data.alimpay.key', '')
            ->assertJsonPath('data.alimpay.hasKey', true);

        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.alimpay.key',
            'value' => json_encode('merchant-secret'),
        ]);
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
