<?php

namespace App\Modules\Payment\Support;

use App\Modules\Config\Services\ConfigService;

/**
 * Epay 当面付通道配置收口。
 *
 * - 敏感值(gateway/pid/key)来自 config/services.php → .env，绝不入库、不写日志。
 * - 运营开关(enabled)走 ConfigService（DB 配置，后台可调），与现有 payment.* 一致。
 * - notify/return URL 在此统一生成，避免散落各处。
 */
class EpayGatewayConfig
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    /** 网关地址，去掉尾部斜杠。例: https://pay.sjiaa.cc.cd */
    public function gateway(): string
    {
        return rtrim((string) config('services.epay.gateway', ''), '/');
    }

    public function pid(): string
    {
        return (string) config('services.epay.pid', '');
    }

    public function key(): string
    {
        return (string) config('services.epay.key', '');
    }

    /** 默认支付方式(type)，当面付走 alipay */
    public function type(): string
    {
        return (string) config('services.epay.type', 'alipay');
    }

    /** 通道是否启用：运营开关(DB) 且 网关/pid/key 均已配置 */
    public function enabled(): bool
    {
        if (! (bool) $this->configs->get('payment.epay_enabled', false)) {
            return false;
        }

        return $this->gateway() !== '' && $this->pid() !== '' && $this->key() !== '';
    }

    /** API 下单地址 mapi.php */
    public function mapiUrl(): string
    {
        return $this->gateway() . '/mapi.php';
    }

    /** 异步通知地址：本系统公开回调路由的绝对 URL（基于 APP_URL） */
    public function notifyUrl(): string
    {
        return route('api.v1.recharge.epay.notify');
    }

    /** 同步跳转地址：用户付款后浏览器回到的前端结果页 */
    public function returnUrl(): string
    {
        return (string) config('services.epay.return_url', '');
    }
}
