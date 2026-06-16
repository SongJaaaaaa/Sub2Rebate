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
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.expireMinutes', 15)
            ->assertJsonPath('data.creditRate', '1');
    }

    public function test_admin_can_update_payment_config(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'qrUrl' => 'https://example.com/alipay-qr.png',
                'displayName' => '张三-支付宝收款码',
                'note' => '付款后请等待审核到账',
                'expireMinutes' => 20,
                'creditRate' => '1.5',
            ])
            ->assertOk()
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
                'qrUrl' => '',
                'displayName' => '张三',
                'note' => 'test',
                'expireMinutes' => 15,
                'creditRate' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', '开启二维码充值时必须提供支付宝二维码');
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