<?php

namespace App\Modules\Payment\Support;

/**
 * 易支付(Epay/彩虹易支付) MD5 签名工具。
 *
 * 算法与 Epay 源码 includes/lib/Payment.php::getSignContent + makeSign 完全一致：
 *   1. 参数按键名 ASCII 升序 ksort
 *   2. 跳过 sign、sign_type、空值(null/''/全空白)、数组
 *   3. 拼成 k=v&k=v...（value 不做 URL 编码）
 *   4. md5( 拼接串 . 商户key )，结果天然小写
 *
 * 仅用于「分销系统 <-> Epay」这条易支付协议通道，绝不与支付宝官方 RSA 回调混用。
 */
class EpaySignature
{
    /**
     * 生成签名。
     *
     * @param  array<string,mixed>  $params  待签参数（含或不含 sign/sign_type 均可，内部会跳过）
     * @param  string  $key  商户密钥
     */
    public static function make(array $params, string $key): string
    {
        return md5(self::signContent($params) . $key);
    }

    /**
     * 校验签名。使用 hash_equals 防时序攻击。
     *
     * @param  array<string,mixed>  $params  收到的全部参数（含 sign）
     * @param  string  $key  商户密钥
     */
    public static function verify(array $params, string $key): bool
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        return hash_equals(self::make($params, $key), $sign);
    }

    /**
     * 构造待签名字符串：ksort 升序，跳过 sign/sign_type/空值/数组，k=v&k=v 拼接。
     *
     * @param  array<string,mixed>  $params
     */
    private static function signContent(array $params): string
    {
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type') {
                continue;
            }
            if (is_array($v)) {
                continue;
            }
            if (self::isEmpty($v)) {
                continue;
            }
            $pairs[] = $k . '=' . $v;
        }

        return implode('&', $pairs);
    }

    /**
     * 与 Epay isEmpty() 对齐：null 或 trim 后为空串视为空。
     */
    private static function isEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
