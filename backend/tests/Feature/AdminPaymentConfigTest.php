<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertJsonPath('data.withdrawDailyLimit', 1)
            ->assertJsonPath('data.epay.enabled', false)
            ->assertJsonPath('data.epay.key', '')
            ->assertJsonPath('data.epay.hasKey', false)
            ->assertJsonPath('data.epay.gatewayUrl', 'https://pay.sjiaa.cc.cd')
            ->assertJsonPath('data.epay.type', 'alipay')
            ->assertJsonPath('data.alipayTransfer.enabled', false)
            ->assertJsonPath('data.alipayTransfer.autoPayEnabled', false)
            ->assertJsonPath('data.alipayTransfer.retryEnabled', false)
            ->assertJsonPath('data.alipayTransfer.retryIntervalMinutes', 5)
            ->assertJsonPath('data.alipayTransfer.retryBatchSize', 50)
            ->assertJsonPath('data.alipayTransfer.gatewayUrl', 'https://openapi.alipay.com/gateway.do')
            ->assertJsonPath('data.alipayTransfer.privateKey', '')
            ->assertJsonPath('data.alipayTransfer.hasPrivateKey', false)
            ->assertJsonPath('data.alipayTransfer.identityType', 'ALIPAY_LOGON_ID')
            ->assertJsonPath('data.alipayTransfer.singleMaxAmount', '500');
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
                'withdrawDailyLimit' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'manual_qr')
            ->assertJsonPath('data.qrUrl', 'https://example.com/alipay-qr.png')
            ->assertJsonPath('data.displayName', '张三-支付宝收款码')
            ->assertJsonPath('data.note', '付款后请等待审核到账')
            ->assertJsonPath('data.expireMinutes', 20)
            ->assertJsonPath('data.creditRate', '1.5')
            ->assertJsonPath('data.withdrawDailyLimit', 3);

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
        $this->assertDatabaseHas('config_items', [
            'key' => 'withdraw.daily_limit',
            'value' => json_encode(3),
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

    public function test_admin_can_update_epay_config_without_key_echo(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'epay',
                'qrUrl' => '',
                'displayName' => '',
                'note' => '手工二维码提示',
                'expireMinutes' => 10,
                'creditRate' => '1',
                'epay' => [
                    'enabled' => true,
                    'pid' => '1001000000000001',
                    'key' => 'merchant-secret',
                    'gatewayUrl' => 'https://pay.example.com',
                    'notifyUrl' => 'https://rebate.example.com/api/v1/recharge/epay/notify',
                    'returnUrl' => 'https://rebate.example.com/recharge',
                    'displayName' => 'Epay 当面付',
                    'sitename' => 'Sub2Rebate',
                    'type' => 'alipay',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'epay')
            ->assertJsonPath('data.channel', 'epay')
            ->assertJsonPath('data.epay.pid', '1001000000000001')
            ->assertJsonPath('data.epay.key', '')
            ->assertJsonPath('data.epay.hasKey', true)
            ->assertJsonPath('data.epay.type', 'alipay');

        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.epay.key',
            'value' => json_encode('merchant-secret'),
        ]);
    }

    public function test_payment_config_maps_legacy_alimpay_values_to_epay(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->seedLegacyAlimpayConfig();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/payment-config')
            ->assertOk()
            ->assertJsonPath('data.mode', 'epay')
            ->assertJsonPath('data.channel', 'epay')
            ->assertJsonPath('data.epay.enabled', true)
            ->assertJsonPath('data.epay.pid', '1001')
            ->assertJsonPath('data.epay.hasKey', true)
            ->assertJsonPath('data.epay.gatewayUrl', 'https://pay.sjiaa.cc.cd')
            ->assertJsonPath('data.epay.notifyUrl', 'https://rebate.sjiaa.cc.cd/api/v1/payments/alimpay/notify')
            ->assertJsonPath('data.epay.returnUrl', 'https://rebate.sjiaa.cc.cd/recharge')
            ->assertJsonPath('data.epay.displayName', '支付宝当面付');
    }

    public function test_admin_can_update_alipay_transfer_config_without_key_echo(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'manual_qr',
                'qrUrl' => 'https://example.com/alipay-qr.png',
                'displayName' => '支付宝收款码',
                'note' => '付款后请等待审核到账',
                'expireMinutes' => 15,
                'creditRate' => '1',
                'alipayTransfer' => [
                    'enabled' => true,
                    'autoPayEnabled' => true,
                    'retryEnabled' => true,
                    'retryIntervalMinutes' => 6,
                    'retryBatchSize' => 20,
                    'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
                    'appId' => '2026000000000001',
                    'privateKey' => 'private-key',
                    'alipayPublicKey' => 'public-key',
                    'singleMaxAmount' => '300',
                    'dailyLimitAmount' => '3000',
                    'identityType' => 'ALIPAY_LOGON_ID',
                    'orderTitle' => '返利提现',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.alipayTransfer.enabled', true)
            ->assertJsonPath('data.alipayTransfer.autoPayEnabled', true)
            ->assertJsonPath('data.alipayTransfer.retryEnabled', true)
            ->assertJsonPath('data.alipayTransfer.retryIntervalMinutes', 6)
            ->assertJsonPath('data.alipayTransfer.retryBatchSize', 20)
            ->assertJsonPath('data.alipayTransfer.appId', '2026000000000001')
            ->assertJsonPath('data.alipayTransfer.privateKey', '')
            ->assertJsonPath('data.alipayTransfer.hasPrivateKey', true)
            ->assertJsonPath('data.alipayTransfer.alipayPublicKey', '')
            ->assertJsonPath('data.alipayTransfer.hasAlipayPublicKey', true)
            ->assertJsonPath('data.alipayTransfer.singleMaxAmount', '300')
            ->assertJsonPath('data.alipayTransfer.dailyLimitAmount', '3000');

        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.alipay_transfer.private_key',
            'value' => json_encode('private-key'),
        ]);
        $this->assertDatabaseHas('config_items', [
            'key' => 'payment.alipay_transfer.alipay_public_key',
            'value' => json_encode('public-key'),
        ]);

        $this->withToken($admin->createToken('test2')->plainTextToken)
            ->putJson('/api/v1/admin/payment-config', [
                'enabled' => true,
                'mode' => 'manual_qr',
                'qrUrl' => 'https://example.com/alipay-qr.png',
                'displayName' => '支付宝收款码',
                'note' => '付款后请等待审核到账',
                'expireMinutes' => 15,
                'creditRate' => '1',
                'alipayTransfer' => [
                    'enabled' => true,
                    'autoPayEnabled' => true,
                    'retryEnabled' => true,
                    'retryIntervalMinutes' => 6,
                    'retryBatchSize' => 20,
                    'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
                    'appId' => '2026000000000001',
                    'privateKey' => '',
                    'alipayPublicKey' => '',
                    'singleMaxAmount' => '300',
                    'dailyLimitAmount' => '3000',
                    'identityType' => 'ALIPAY_LOGON_ID',
                    'orderTitle' => '返利提现',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.alipayTransfer.hasPrivateKey', true)
            ->assertJsonPath('data.alipayTransfer.hasAlipayPublicKey', true);
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

    private function seedLegacyAlimpayConfig(): void
    {
        foreach ([
            'payment.mode' => ['type' => 'string', 'sort' => 90],
            'payment.alimpay.enabled' => ['type' => 'bool', 'sort' => 96],
            'payment.alimpay.pid' => ['type' => 'string', 'sort' => 97],
            'payment.alimpay.key' => ['type' => 'string', 'sort' => 98],
            'payment.alimpay.gateway_url' => ['type' => 'string', 'sort' => 99],
            'payment.alimpay.notify_url' => ['type' => 'string', 'sort' => 100],
            'payment.alimpay.return_url' => ['type' => 'string', 'sort' => 101],
            'payment.alimpay.display_name' => ['type' => 'string', 'sort' => 102],
            'payment.alimpay.sitename' => ['type' => 'string', 'sort' => 103],
        ] as $key => $meta) {
            DB::table('config_items')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'payment',
                    'name' => $key,
                    'type' => $meta['type'],
                    'value' => json_encode(match ($key) {
                        'payment.mode' => 'alimpay_qr',
                        'payment.alimpay.enabled' => true,
                        'payment.alimpay.pid' => '1001',
                        'payment.alimpay.key' => 'legacy-secret',
                        'payment.alimpay.gateway_url' => 'https://pay.sjiaa.cc.cd',
                        'payment.alimpay.notify_url' => 'https://rebate.sjiaa.cc.cd/api/v1/payments/alimpay/notify',
                        'payment.alimpay.return_url' => 'https://rebate.sjiaa.cc.cd/recharge',
                        'payment.alimpay.display_name' => '支付宝当面付',
                        'payment.alimpay.sitename' => 'Sub2Rebate',
                    }),
                    'tips' => '',
                    'sort' => $meta['sort'],
                    'is_public' => true,
                ]
            );
        }

        foreach ([
            'payment.mode' => 'alimpay_qr',
            'payment.alimpay.enabled' => true,
            'payment.alimpay.pid' => '1001',
            'payment.alimpay.key' => 'legacy-secret',
            'payment.alimpay.gateway_url' => 'https://pay.sjiaa.cc.cd',
            'payment.alimpay.notify_url' => 'https://rebate.sjiaa.cc.cd/api/v1/payments/alimpay/notify',
            'payment.alimpay.return_url' => 'https://rebate.sjiaa.cc.cd/recharge',
            'payment.alimpay.display_name' => '支付宝当面付',
            'payment.alimpay.sitename' => 'Sub2Rebate',
        ] as $key => $value) {
            $this->assertDatabaseHas('config_items', [
                'key' => $key,
                'value' => json_encode($value),
            ], 'sqlite');
        }
    }
}
