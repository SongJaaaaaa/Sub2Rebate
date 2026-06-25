<?php

namespace Tests\Unit;

use App\Modules\Payment\Support\EpaySignature;
use PHPUnit\Framework\TestCase;

class EpaySignatureTest extends TestCase
{
    private const KEY = 'testMerchantKey123';

    public function test_make_matches_independently_constructed_string(): void
    {
        // 乱序传入，验证 make() 内部会 ksort 升序
        $params = [
            'pid' => '1001',
            'trade_no' => '20260625120000',
            'out_trade_no' => 'RC20260625ABCD',
            'type' => 'alipay',
            'name' => 'recharge',
            'money' => '1.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];

        // 手工按 ASCII 升序拼接（基准，不依赖被测代码的排序逻辑）
        $expectedStr = 'money=1.00'
            . '&name=recharge'
            . '&out_trade_no=RC20260625ABCD'
            . '&pid=1001'
            . '&trade_no=20260625120000'
            . '&trade_status=TRADE_SUCCESS'
            . '&type=alipay';
        $expected = md5($expectedStr . self::KEY);

        $this->assertSame($expected, EpaySignature::make($params, self::KEY));
    }

    public function test_make_excludes_sign_and_sign_type(): void
    {
        $base = ['pid' => '1001', 'money' => '1.00'];

        $withSign = $base + ['sign' => 'whatever', 'sign_type' => 'MD5'];

        // 带上 sign/sign_type 不应影响结果
        $this->assertSame(
            EpaySignature::make($base, self::KEY),
            EpaySignature::make($withSign, self::KEY),
        );
    }

    public function test_make_skips_empty_and_array_values(): void
    {
        $base = ['pid' => '1001', 'money' => '1.00'];

        $withEmpty = $base + [
            'param' => '',          // 空串跳过
            'buyer' => null,        // null 跳过
            'extra' => '   ',       // 全空白跳过
            'nested' => ['x' => 1], // 数组跳过
        ];

        $this->assertSame(
            EpaySignature::make($base, self::KEY),
            EpaySignature::make($withEmpty, self::KEY),
        );
    }

    public function test_verify_round_trip(): void
    {
        $params = [
            'pid' => '1001',
            'out_trade_no' => 'RC20260625ABCD',
            'money' => '1.00',
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
        ];
        $params['sign'] = EpaySignature::make($params, self::KEY);

        $this->assertTrue(EpaySignature::verify($params, self::KEY));
    }

    public function test_verify_fails_on_tampered_amount(): void
    {
        $params = [
            'pid' => '1001',
            'out_trade_no' => 'RC20260625ABCD',
            'money' => '1.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];
        $params['sign'] = EpaySignature::make($params, self::KEY);

        // 篡改金额后验签必须失败
        $params['money'] = '999.00';
        $this->assertFalse(EpaySignature::verify($params, self::KEY));
    }

    public function test_verify_fails_on_missing_or_wrong_sign(): void
    {
        $params = ['pid' => '1001', 'money' => '1.00'];

        $this->assertFalse(EpaySignature::verify($params, self::KEY)); // 无 sign
        $this->assertFalse(EpaySignature::verify($params + ['sign' => 'deadbeef'], self::KEY)); // 错 sign
        $this->assertFalse(EpaySignature::verify(
            $params + ['sign' => EpaySignature::make($params, self::KEY)],
            'wrongKey',
        )); // 错 key
    }
}
