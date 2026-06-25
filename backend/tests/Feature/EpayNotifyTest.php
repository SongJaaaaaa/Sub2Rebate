<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Support\EpaySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpayNotifyTest extends TestCase
{
    use RefreshDatabase;

    private const PID = '20001';
    private const KEY = 'epayTestKey';

    public function test_valid_notify_credits_user_and_marks_paid(): void
    {
        $user = $this->prepare();
        $order = $this->pendingEpayOrder($user, '100');

        $this->fakeSub2Api($user->id);

        $resp = $this->get($this->signedNotifyUrl($order->order_no, '100.00'));
        $resp->assertOk();
        $this->assertSame('success', $resp->getContent());

        $this->assertDatabaseHas('recharge_orders', [
            'order_no' => $order->order_no,
            'status' => RechargeOrder::STATUS_PAID,
            'epay_trade_no' => 'EPAY-TXN-1',
            'sub2_balance_after' => '105.000000', // Sub2API 返回的新余额已记入付款日志
        ]);
        $this->assertDatabaseHas('rebate_events', [
            'user_id' => $user->id,
            'source_type' => 'sub2rebate.recharge_order',
            'source_id' => 'recharge-order-' . $order->order_no,
        ]);
        // Sub2API 加余额被调用一次
        Http::assertSentCount(1);
    }

    public function test_replayed_notify_does_not_double_credit(): void
    {
        $user = $this->prepare();
        $order = $this->pendingEpayOrder($user, '100');
        $this->fakeSub2Api($user->id);

        $url = $this->signedNotifyUrl($order->order_no, '100.00');

        $this->get($url)->assertOk();
        $second = $this->get($url);
        $second->assertOk();
        $this->assertSame('success', $second->getContent());

        // 只入账一次：rebate_events 仅 1 条，Sub2API 第二次走幂等短路未再请求
        $this->assertSame(1, DB::table('rebate_events')->where('user_id', $user->id)->count());
        Http::assertSentCount(1);
    }

    public function test_tampered_sign_is_rejected(): void
    {
        $user = $this->prepare();
        $order = $this->pendingEpayOrder($user, '100');
        $this->fakeSub2Api($user->id);

        $params = $this->notifyParams($order->order_no, '100.00');
        $params['sign'] = 'deadbeef'; // 错误签名

        $resp = $this->get(route('api.v1.recharge.epay.notify') . '?' . http_build_query($params));
        $resp->assertOk();
        $this->assertSame('fail', $resp->getContent());

        $this->assertDatabaseHas('recharge_orders', [
            'order_no' => $order->order_no,
            'status' => RechargeOrder::STATUS_PENDING,
        ]);
        Http::assertNothingSent();
    }

    public function test_underpaid_amount_is_rejected(): void
    {
        $user = $this->prepare();
        $order = $this->pendingEpayOrder($user, '100');
        $this->fakeSub2Api($user->id);

        // 实付 1 元 < 订单 100 元
        $resp = $this->get($this->signedNotifyUrl($order->order_no, '1.00'));
        $resp->assertOk();
        $this->assertSame('fail', $resp->getContent());

        $this->assertDatabaseHas('recharge_orders', [
            'order_no' => $order->order_no,
            'status' => RechargeOrder::STATUS_PENDING,
        ]);
        Http::assertNothingSent();
    }

    // ---- helpers ----

    private function prepare(): User
    {
        config([
            'services.epay.pid' => self::PID,
            'services.epay.key' => self::KEY,
            'services.epay.type' => 'alipay',
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
        app(ConfigService::class)->ensureDefaults();

        return User::query()->create([
            'id' => 1001,
            'username' => 'payer',
            'email' => 'payer@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    private function pendingEpayOrder(User $user, string $amount): RechargeOrder
    {
        return RechargeOrder::query()->create([
            'user_id' => $user->id,
            'order_no' => 'RC' . $amount . 'EPAYTEST',
            'channel' => 'epay',
            'amount' => $amount,
            'bonus_amount' => '5.000000',
            'credit_amount' => (string) ((float) $amount + 5),
            'status' => RechargeOrder::STATUS_PENDING,
            'expire_at' => now()->addMinutes(15),
        ]);
    }

    private function fakeSub2Api(int $userId): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/users/' . $userId . '/balance' => Http::response([
                'code' => 0,
                'data' => ['id' => $userId, 'balance' => 105, 'total_recharged' => 105],
            ]),
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function notifyParams(string $outTradeNo, string $money): array
    {
        $params = [
            'pid' => self::PID,
            'trade_no' => 'EPAY-TXN-1',
            'out_trade_no' => $outTradeNo,
            'type' => 'alipay',
            'name' => '账户充值',
            'money' => $money,
            'trade_status' => 'TRADE_SUCCESS',
        ];
        $params['sign'] = EpaySignature::make($params, self::KEY);
        $params['sign_type'] = 'MD5';

        return $params;
    }

    private function signedNotifyUrl(string $outTradeNo, string $money): string
    {
        return route('api.v1.recharge.epay.notify') . '?' . http_build_query($this->notifyParams($outTradeNo, $money));
    }
}
