<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Payment\Services\RechargeEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RechargeEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manual_recharge_event_with_snapshot(): void
    {
        $admin = $this->user(1001, 'admin', 'admin');
        $target = $this->user(1002, 'user');

        DB::table('config_items')->insert([
            'key' => 'payment.cny_to_credit_rate',
            'group' => 'payment',
            'name' => '人民币额度换算比例',
            'type' => 'decimal',
            'value' => json_encode('2'),
            'tips' => '测试换算比例',
            'sort' => 1,
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(ConfigService::class)->forget();

        $result = app(RechargeEventService::class)->createManual($admin, $target, [
            'source_type' => 'manual_admin',
            'source_id' => 'manual-1002-001',
            'source_amount' => '100',
            'remark' => '后台补录测试充值',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['created']);

        $this->assertDatabaseHas('payment_records', [
            'user_id' => 1002,
            'source_type' => 'manual_admin',
            'source_id' => 'manual-1002-001',
            'standard_amount' => '100',
            'credit_amount' => '200',
            'operator_user_id' => 1001,
        ]);

        $this->assertDatabaseHas('rebate_events', [
            'user_id' => 1002,
            'source_type' => 'manual_admin',
            'source_id' => 'manual-1002-001',
            'status' => RebateEvent::STATUS_PENDING,
            'standard_amount' => '100',
            'credit_amount' => '200',
            'operator_user_id' => 1001,
        ]);

        $event = RebateEvent::query()->firstOrFail();
        $this->assertSame('2.000000', $event->config_snapshot['payment.cny_to_credit_rate']);
    }

    public function test_source_type_and_source_id_are_idempotent(): void
    {
        $admin = $this->user(1001, 'admin', 'admin');
        $target = $this->user(1002, 'user');
        $svc = app(RechargeEventService::class);

        $first = $svc->createManual($admin, $target, [
            'source_type' => 'sub2api.redeem_codes',
            'source_id' => 'redeem-001',
            'source_amount' => '50',
            'remark' => '兑换码补录',
        ]);
        $second = $svc->createManual($admin, $target, [
            'source_type' => 'sub2api.redeem_codes',
            'source_id' => 'redeem-001',
            'source_amount' => '50',
            'remark' => '重复请求',
        ]);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, DB::table('payment_records')->count());
        $this->assertSame(1, DB::table('rebate_events')->count());
    }

    public function test_manual_event_requires_admin_and_remark(): void
    {
        $user = $this->user(1001, 'user');
        $target = $this->user(1002, 'target');
        $svc = app(RechargeEventService::class);

        $forbidden = $svc->createManual($user, $target, [
            'source_type' => 'manual_admin',
            'source_id' => 'manual-1',
            'source_amount' => '10',
            'remark' => '普通用户尝试',
        ]);

        $this->assertFalse($forbidden['ok']);
        $this->assertSame(403, $forbidden['status']);

        $admin = $this->user(1003, 'admin', 'admin');
        $missingRemark = $svc->createManual($admin, $target, [
            'source_type' => 'manual_admin',
            'source_id' => 'manual-2',
            'source_amount' => '10',
            'remark' => '',
        ]);

        $this->assertFalse($missingRemark['ok']);
        $this->assertSame(400, $missingRemark['status']);
    }

    public function test_invalid_event_data_is_rejected(): void
    {
        $target = $this->user(1002, 'target');

        $result = app(RechargeEventService::class)->createRechargeEvent([
            'user_id' => $target->id,
            'source_type' => '',
            'source_id' => '',
            'source_amount' => '0',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['status']);
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
