<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Services\AdminWithdrawService;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Rebate\Models\RebateBalance;
use App\Modules\Withdraw\Models\WithdrawAccount;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_then_mark_paid(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $svc = app(AdminWithdrawService::class);

        $approve = $svc->approve($admin, $record, '审核通过');
        $this->assertTrue($approve['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);

        $paid = $svc->markPaid($admin, $record->refresh(), '已线下打款');
        $this->assertTrue($paid['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => '0',
            'withdrawn_amount' => '100',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'withdraw',
            'action' => 'withdraw.approve',
            'remark' => '审核通过',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $user->id,
            'module' => 'withdraw',
            'action' => 'withdraw.mark_paid',
            'remark' => '已线下打款',
        ]);
    }

    public function test_admin_can_reject_and_unfreeze_balance(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');

        $result = app(AdminWithdrawService::class)->reject($admin, $record, '资料不一致');

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_REJECTED,
            'reject_reason' => '资料不一致',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '150',
            'frozen_amount' => '0',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdraw.reject',
            'remark' => '资料不一致',
        ]);
    }

    public function test_admin_withdraw_actions_require_admin_and_remark(): void
    {
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $svc = app(AdminWithdrawService::class);

        $notAdmin = $svc->approve($user, $record, '尝试审核');
        $this->assertFalse($notAdmin['ok']);
        $this->assertSame(403, $notAdmin['status']);

        $admin = $this->user(9001, 'admin', 'admin');
        $missingRemark = $svc->approve($admin, $record, '');
        $this->assertFalse($missingRemark['ok']);
        $this->assertSame('后台敏感操作必须填写备注', $missingRemark['message']);
    }

    public function test_withdraw_status_cannot_skip_to_paid(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');

        $result = app(AdminWithdrawService::class)->markPaid($admin, $record, '直接打款');

        $this->assertFalse($result['ok']);
        $this->assertSame('只有已审核通过的提现可以标记打款', $result['message']);
    }

    public function test_mark_paid_can_call_alipay_transfer(): void
    {
        Http::fake([
            'https://openapi.alipay.com/gateway.do' => Http::response([
                'alipay_fund_trans_uni_transfer_response' => [
                    'code' => '10000',
                    'msg' => 'Success',
                    'order_id' => '202606260001',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $this->setAlipayTransferConfig();
        app(AdminWithdrawService::class)->approve($admin, $record, '审核通过');

        $result = app(AdminWithdrawService::class)->markPaid($admin, $record->refresh(), '自动打款');

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) use ($record): bool {
            $params = $request->data();
            $biz = json_decode((string) ($params['biz_content'] ?? ''), true);

            return $request->url() === 'https://openapi.alipay.com/gateway.do'
                && ($params['app_id'] ?? '') === '2026000000000001'
                && ($params['method'] ?? '') === 'alipay.fund.trans.uni.transfer'
                && ($params['sign_type'] ?? '') === 'RSA2'
                && ($params['sign'] ?? '') !== ''
                && ($biz['out_biz_no'] ?? '') === 'SRWD'.$record->id
                && ($biz['trans_amount'] ?? '') === '100.00'
                && ($biz['payee_info']['identity'] ?? '') === 'demo@example.com'
                && ($biz['payee_info']['name'] ?? '') === '张三';
        });
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PAID,
            'payout_trade_no' => '202606260001',
            'payout_error' => null,
        ]);
    }

    public function test_alipay_transfer_failure_keeps_record_approved_for_retry(): void
    {
        Http::fake([
            'https://openapi.alipay.com/gateway.do' => Http::response([
                'alipay_fund_trans_uni_transfer_response' => [
                    'code' => '40004',
                    'msg' => 'Business Failed',
                    'sub_msg' => '收款账号姓名不匹配',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $this->setAlipayTransferConfig();
        app(AdminWithdrawService::class)->approve($admin, $record, '审核通过');

        $result = app(AdminWithdrawService::class)->markPaid($admin, $record->refresh(), '自动打款');

        $this->assertFalse($result['ok']);
        $this->assertSame('支付宝返回失败：收款账号姓名不匹配', $result['message']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_APPROVED,
            'payout_error' => '支付宝返回失败：收款账号姓名不匹配',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => '100',
            'withdrawn_amount' => '0',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdraw.payout_failed',
            'remark' => '自动打款',
        ]);
    }

    public function test_approve_can_auto_pay_when_auto_pay_enabled(): void
    {
        Http::fake([
            'https://openapi.alipay.com/gateway.do' => Http::response([
                'alipay_fund_trans_uni_transfer_response' => [
                    'code' => '10000',
                    'msg' => 'Success',
                    'order_id' => 'AUTO202606260001',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $this->setAlipayTransferConfig(autoPay: true);

        $result = app(AdminWithdrawService::class)->approve($admin, $record, '审核通过');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['warning']);
        $this->assertSame(WithdrawRecord::STATUS_PAID, $result['record']->status);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PAID,
            'payout_trade_no' => 'AUTO202606260001',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => '0',
            'withdrawn_amount' => '100',
        ]);
    }

    public function test_approve_auto_pay_failure_keeps_approved_record(): void
    {
        Http::fake([
            'https://openapi.alipay.com/gateway.do' => Http::response([
                'alipay_fund_trans_uni_transfer_response' => [
                    'code' => '40004',
                    'msg' => 'Business Failed',
                    'sub_msg' => '余额不足',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $this->setAlipayTransferConfig(autoPay: true);

        $result = app(AdminWithdrawService::class)->approve($admin, $record, '审核通过');

        $this->assertTrue($result['ok']);
        $this->assertSame('支付宝返回失败：余额不足', $result['warning']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_APPROVED,
            'payout_error' => '支付宝返回失败：余额不足',
        ]);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => '100',
            'withdrawn_amount' => '0',
        ]);
    }

    public function test_auto_payout_command_retries_failed_approved_records(): void
    {
        Http::fake([
            'https://openapi.alipay.com/gateway.do' => Http::response([
                'alipay_fund_trans_uni_transfer_response' => [
                    'code' => '10000',
                    'msg' => 'Success',
                    'order_id' => 'RETRY202606260001',
                ],
            ]),
        ]);

        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        $this->setAlipayTransferConfig(retry: true);
        app(AdminWithdrawService::class)->approve($admin, $record, '审核通过');
        $record->refresh()->forceFill([
            'payout_error' => '上次失败',
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $this->artisan('withdraw:process-auto-payout --limit=10')
            ->assertExitCode(0);

        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PAID,
            'payout_trade_no' => 'RETRY202606260001',
            'payout_error' => null,
        ]);
    }

    public function test_reject_fails_when_balance_is_missing(): void
    {
        $admin = $this->user(9001, 'admin', 'admin');
        $user = $this->user(1001, 'user');
        $record = $this->pendingWithdraw($user, '100');
        RebateBalance::query()->where('user_id', $user->id)->delete();

        $result = app(AdminWithdrawService::class)->reject($admin, $record, '资料不一致');

        $this->assertFalse($result['ok']);
        $this->assertSame('返利余额不存在', $result['message']);
        $this->assertDatabaseHas('withdraw_records', [
            'id' => $record->id,
            'status' => WithdrawRecord::STATUS_PENDING,
        ]);
    }

    private function pendingWithdraw(User $user, string $amount): WithdrawRecord
    {
        RebateBalance::query()->create([
            'user_id' => $user->id,
            'available_amount' => '50',
            'frozen_amount' => $amount,
            'withdrawn_amount' => '0',
        ]);

        $account = WithdrawAccount::query()->create([
            'user_id' => $user->id,
            'type' => 'alipay',
            'real_name' => '张三',
            'account_no' => 'demo@example.com',
        ]);

        return WithdrawRecord::query()->create([
            'user_id' => $user->id,
            'withdraw_account_id' => $account->id,
            'type' => WithdrawRecord::TYPE_ALIPAY,
            'amount' => $amount,
            'status' => WithdrawRecord::STATUS_PENDING,
            'account_type' => 'alipay',
            'account_no' => 'demo@example.com',
            'real_name' => '张三',
            'remark' => '提现',
        ]);
    }

    private function user(int $id, string $username, string $role = 'user'): User
    {
        return User::query()->firstOrCreate(
            ['id' => $id],
            [
                'username' => $username,
                'email' => $username.'@example.com',
                'role' => $role,
                'status' => 'active',
            ]
        );
    }

    private function setAlipayTransferConfig(bool $autoPay = false, bool $retry = false): void
    {
        app(ConfigService::class)->ensureDefaults();

        $items = [
            'payment.alipay_transfer.enabled' => true,
            'payment.alipay_transfer.auto_pay_enabled' => $autoPay,
            'payment.alipay_transfer.retry_enabled' => $retry,
            'payment.alipay_transfer.retry_interval_minutes' => 5,
            'payment.alipay_transfer.retry_batch_size' => 50,
            'payment.alipay_transfer.gateway_url' => 'https://openapi.alipay.com/gateway.do',
            'payment.alipay_transfer.app_id' => '2026000000000001',
            'payment.alipay_transfer.private_key' => $this->privateKey(),
            'payment.alipay_transfer.alipay_public_key' => 'public-key',
            'payment.alipay_transfer.single_max_amount' => '500',
            'payment.alipay_transfer.daily_limit_amount' => '5000',
            'payment.alipay_transfer.identity_type' => 'ALIPAY_LOGON_ID',
            'payment.alipay_transfer.order_title' => '返利提现',
        ];

        foreach ($items as $key => $value) {
            DB::table('config_items')->where('key', $key)->update(['value' => json_encode($value)]);
        }

        app(ConfigService::class)->forget();
    }

    private function privateKey(): string
    {
        return <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCqHxJRg21MEa82
nvcDdNi+Jqp7Dnb2YJxHpGMDrmRWvsC1jxHXP4m0ivyH5emwbqKDQYtEe6hM/001
1KnNiN05qdP46AHxWEDYbv31qsVYvRaEI5yT1Vu6mIujUINx/r9tLDCaSMKLlKdi
VT//y9RfzAvbr+1EgtfPT+ehP8FwAxBnJ9NhbztW5b3E+DTreQZsztJe5D1OoIcD
bdmJLOMmcPigqrwmAHiXDdHDd6IB514OCknVs10dcMRwlRC54EX8N0Nn7cCS8+q9
+dbD5F7fWlMM6/I4qkZDcD2K3ZJoW9DypsQ8yPlKAtKtxgr/Lrad5cB7KH9wtchr
snovW09pAgMBAAECggEAU1qcECJ0OBRmJD4pS6FB3ZF2cIr60OcvS48JELGu6i3W
oF70X+H65+I9r5yALvlWWTeRNEHeibOBskF73YAU3P8QJGIRKZ6TTzi66Fb/EOa9
FIuaWXjt0/SQXrnBXeZzBtvjCIVkBR4WEYExtXS6nyGMId0GRU5SzXoaRRDHB8k7
3ChEHIva8Of/QkB1J+vxCdvD7NmmIEt/DjZTaRgBdF714HDpYqidZ6DrQdxcYy5K
EXsfZ9pb/vXv+xRNKmFa3S89ll/nXFiTUNWzPmFrHXwj7OOmRodR8G6pH7Ri/6cp
Ef91MmbOXHexpjGSUgNPsgozhug1Kj8s+Bhv4D9t7QKBgQDZzrTrjUop8hQRHpVB
IEd50khx1aIWIsgZWWSPkB+sinyOyAt6i7R66LlpOq3kyaBFOBx9/PTJW8Mf1syE
bRX9Q48To0NIT8wD9AG5opbaFIUlo4TmRuPO9KE8MKYBpmMfIsdtsFUjuCJ9iJJI
Qc6O44HHb03kqXTpwZ+ESY+EFwKBgQDH88FPu9ho8rIF28eTdfnzThJZa4BECBer
kmEZcRygoWN5Z633dJJat2AxP3QXGo0p+mB+zVZ/2NixsPDYp1X/WwFHhVqEaQJS
lGs3x+99Kd54SasCdLde4/lyFBaWEy86RfyKCwubDwB325mIFSFRyZIYp+IAmwEQ
vIrxz3J4fwKBgA4YO0r29LKsMLI+6WeygA2ZFwkOyxNlos9JIqHLsNEIkTDoLx36
Bm7huoXdvz0L8ywnimh4wxp4rrLTwp5bNM4T3iFmMkduqoQi+S2bIOnx4//gigwg
0EMnP3vWphd7PfTY2lD11TyfgNPgz56Pa5+Bh3dxc3f1o1QxLHJyFDB3AoGBAIRd
6HYZO41WROW99eO3sQ0RfPI3SUVAOjM5hxApojLwRALl0PPE9vIY/RP9FqQIzrSg
bGrlEkM2UVVodjhmpnaST0mCjUakoYX7fPMDZ5ZrNjxZQF0y2QV3U/XiOIWHE7e3
BxR3dWpjxwKRnJTKsDENiKrL0MBn3I+w0SZ9FyGPAoGAN79+V+S0o3+KE8zSaRUj
U3BBq+P9uM1u4dsdeX+8+QMYUiV3hty/+t6qbZezWzHQhyl5lS63xrB4yr2KzFCK
ayEMGGfR4+FLVkJ13aKfQCbV3ClwTwPFFqjyriK9qoksanRqyvlzr25SFkMlwQMx
uK/lxiSLqF6cCSOdCw6ztc0=
-----END PRIVATE KEY-----
KEY;
    }
}
