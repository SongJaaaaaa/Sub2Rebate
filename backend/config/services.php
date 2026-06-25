<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    // Epay（彩虹易支付）当面付通道。敏感值仅来自 .env，代码与示例中不写明文。
    'epay' => [
        'gateway' => env('EPAY_GATEWAY', ''),     // 例: https://pay.sjiaa.cc.cd
        'pid' => env('EPAY_PID', ''),             // 商户ID
        'key' => env('EPAY_KEY', ''),             // 商户密钥（验签/签名用，切勿入库/入日志）
        'type' => env('EPAY_TYPE', 'alipay'),     // 默认支付方式
        'return_url' => env('EPAY_RETURN_URL', ''), // 用户付款后浏览器跳转的前端结果页
    ],

];
