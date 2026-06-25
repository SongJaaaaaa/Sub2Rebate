<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Payment\Models\RechargeOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRechargeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_recharge_orders(): void
    {
        $admin = $this->mk(9001, 'admin', 'admin');
        $this->mk(1001, 'alice');
        $this->mk(1002, 'bob');

        $this->order(1001, 'RC-EPAY-001', 'epay', 'paid', 'EPAYTXN-AAA');
        $this->order(1002, 'RC-MANUAL-002', 'alipay', 'submitted', null);

        $token = $admin->createToken('t')->plainTextToken;

        // 全量
        $this->withToken($token)->getJson('/api/v1/admin/recharge-orders')
            ->assertOk()->assertJsonPath('data.total', 2);

        // 按订单号搜
        $this->withToken($token)->getJson('/api/v1/admin/recharge-orders?keyword=EPAY-001')
            ->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.orderNo', 'RC-EPAY-001');

        // 按 Epay 流水号搜
        $this->withToken($token)->getJson('/api/v1/admin/recharge-orders?keyword=EPAYTXN-AAA')
            ->assertOk()->assertJsonPath('data.total', 1);

        // 按用户名搜
        $this->withToken($token)->getJson('/api/v1/admin/recharge-orders?keyword=bob')
            ->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.userId', 1002);

        // 按通道筛选
        $this->withToken($token)->getJson('/api/v1/admin/recharge-orders?channel=epay')
            ->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.orderNo', 'RC-EPAY-001');
    }

    private function mk(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function order(int $userId, string $orderNo, string $channel, string $status, ?string $epayTradeNo): RechargeOrder
    {
        return RechargeOrder::query()->create([
            'user_id' => $userId,
            'order_no' => $orderNo,
            'channel' => $channel,
            'amount' => '100.000000',
            'bonus_amount' => '5.000000',
            'credit_amount' => '105.000000',
            'status' => $status,
            'epay_trade_no' => $epayTradeNo,
            'expire_at' => now()->addMinutes(15),
        ]);
    }
}
