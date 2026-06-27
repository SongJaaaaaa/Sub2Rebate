<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RechargeOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_submit_recharge_order(): void
    {
        $user = $this->user(1001, 'user');
        $this->setRechargeConfig();

        $token = $user->createToken('test')->plainTextToken;

        $create = $this->withToken($token)
            ->postJson('/api/v1/recharge/orders', [
                'amount' => '100',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.bonusAmount', '5.00')
            ->assertJsonPath('data.creditAmount', '105.00')
            ->assertJsonPath('data.status', 'pending');

        $id = (int) $create->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/recharge/orders/'.$id.'/submit', [
                'payerName' => '张三',
                'payerAccount' => 'alipay-demo',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.payerName', '张三')
            ->assertJsonPath('data.payerAccount', 'alipay-demo');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $id,
            'user_id' => $user->id,
            'status' => RechargeOrder::STATUS_SUBMITTED,
            'amount' => '100',
            'bonus_amount' => '5',
            'credit_amount' => '105',
        ]);
    }

    public function test_user_can_create_recharge_order_below_ten_yuan(): void
    {
        $user = $this->user(1001, 'user');
        $this->setRechargeConfig();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/recharge/orders', [
                'amount' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount', '1.00')
            ->assertJsonPath('data.creditAmount', '1.00')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_admin_can_approve_recharge_order_and_create_rebate_event(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/1001' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 15],
            ]),
            'https://sub2api.test/api/v1/admin/users/1001/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => 1001, 'balance' => 120, 'total_recharged' => 120],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $this->setRechargeConfig();

        $order = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606160001',
            'channel' => 'alipay',
            'amount' => '100.000000',
            'bonus_amount' => '5.000000',
            'credit_amount' => '105.000000',
            'status' => RechargeOrder::STATUS_SUBMITTED,
            'payer_name' => '张三',
            'payer_account' => 'alipay-demo',
            'submitted_at' => now(),
            'expire_at' => now()->addMinutes(15),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/recharge-orders/'.$order->id.'/approve', [
                'remark' => '已核对支付宝收款',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewRemark', '已核对支付宝收款');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://sub2api.test/api/v1/admin/users/1001/balance'
                && $request->hasHeader('x-api-key', 'secret-key')
                && (float) ($data['balance'] ?? 0) === 105.0
                && ($data['operation'] ?? '') === 'add';
        });

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'sub2_balance_before' => '15',
            'sub2_balance_after' => '120',
        ]);
        $this->assertDatabaseHas('payment_records', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.recharge_order',
            'source_id' => 'recharge-order-RC202606160001',
            'standard_amount' => '100',
            'credit_amount' => '105',
        ]);
        $this->assertDatabaseHas('rebate_events', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.recharge_order',
            'source_id' => 'recharge-order-RC202606160001',
            'standard_amount' => '100',
            'credit_amount' => '105',
        ]);
    }

    public function test_admin_can_reject_recharge_order(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $order = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606160002',
            'channel' => 'alipay',
            'amount' => '50.000000',
            'bonus_amount' => '0.000000',
            'credit_amount' => '50.000000',
            'status' => RechargeOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'expire_at' => now()->addMinutes(15),
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/recharge-orders/'.$order->id.'/reject', [
                'remark' => '付款信息不匹配',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reviewRemark', '付款信息不匹配');

        $this->assertDatabaseHas('recharge_orders', [
            'id' => $order->id,
            'status' => RechargeOrder::STATUS_REJECTED,
        ]);
    }

    public function test_user_recharge_records_support_status_and_date_filters(): void
    {
        $user = $this->user(1001, 'user');
        $other = $this->user(1002, 'other');

        $approved = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606260001',
            'channel' => 'alipay',
            'amount' => '50.000000',
            'bonus_amount' => '0.000000',
            'credit_amount' => '50.000000',
            'status' => RechargeOrder::STATUS_APPROVED,
            'expire_at' => now()->addMinutes(15),
        ]);
        $approved->forceFill([
            'created_at' => '2026-06-26 10:00:00',
            'updated_at' => '2026-06-26 10:00:00',
        ])->save();

        $pending = RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC202606250001',
            'channel' => 'alipay',
            'amount' => '100.000000',
            'bonus_amount' => '5.000000',
            'credit_amount' => '105.000000',
            'status' => RechargeOrder::STATUS_PENDING,
            'expire_at' => now()->addMinutes(15),
        ]);
        $pending->forceFill([
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
        ])->save();

        $otherOrder = RechargeOrder::query()->create([
            'user_id' => $other->id,
            'order_no' => 'RC202606260002',
            'channel' => 'alipay',
            'amount' => '200.000000',
            'bonus_amount' => '15.000000',
            'credit_amount' => '215.000000',
            'status' => RechargeOrder::STATUS_APPROVED,
            'expire_at' => now()->addMinutes(15),
        ]);
        $otherOrder->forceFill([
            'created_at' => '2026-06-26 11:00:00',
            'updated_at' => '2026-06-26 11:00:00',
        ])->save();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/recharge/orders?status=approved&startDate=2026-06-26&endDate=2026-06-26')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.orderNo', 'RC202606260001');
    }

    private function setRechargeConfig(): void
    {
        app(ConfigService::class)->ensureDefaults();

        DB::table('config_items')->where('key', 'payment.qr_enabled')->update(['value' => json_encode(true)]);
        DB::table('config_items')->where('key', 'payment.alipay_qr_url')->update(['value' => json_encode('https://example.com/alipay-qr.png')]);
        DB::table('config_items')->where('key', 'payment.alipay_display_name')->update(['value' => json_encode('张三支付宝')]);
        DB::table('config_items')->where('key', 'payment.qr_note')->update(['value' => json_encode('付款时请备注订单号')]);
        DB::table('config_items')->where('key', 'payment.order_expire_minutes')->update(['value' => json_encode(15)]);
        app(ConfigService::class)->forget();
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
