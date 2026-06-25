<?php

namespace App\Modules\Payment\Services;

use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;

class AliMPayService
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function config(): array
    {
        $gatewayUrl = rtrim(trim((string) $this->configs->get('payment.alimpay.gateway_url', '')), '/');

        return [
            'enabled' => (bool) $this->configs->get('payment.alimpay.enabled', false),
            'pid' => trim((string) $this->configs->get('payment.alimpay.pid', '')),
            'key' => trim((string) $this->configs->get('payment.alimpay.key', '')),
            'gatewayUrl' => $gatewayUrl,
            'notifyUrl' => trim((string) $this->configs->get('payment.alimpay.notify_url', '')),
            'returnUrl' => trim((string) $this->configs->get('payment.alimpay.return_url', '')),
            'displayName' => trim((string) $this->configs->get('payment.alimpay.display_name', 'AliMPay/经营码')),
            'sitename' => trim((string) $this->configs->get('payment.alimpay.sitename', 'Sub2Rebate')),
        ];
    }

    public function canCreate(): ?string
    {
        $config = $this->config();
        if (! $config['enabled']) {
            return 'AliMPay 通道未启用';
        }

        foreach (['pid' => '商户 PID', 'key' => '商户 Key', 'gatewayUrl' => 'AliMPay 地址', 'notifyUrl' => '回调地址', 'returnUrl' => '返回地址'] as $field => $name) {
            if ($config[$field] === '') {
                return 'AliMPay '.$name.'未配置';
            }
        }

        return null;
    }

    public function payUrl(RechargeOrder $order): string
    {
        $config = $this->config();
        $params = [
            'pid' => $config['pid'],
            'type' => 'alipay',
            'out_trade_no' => (string) $order->out_trade_no,
            'notify_url' => $config['notifyUrl'],
            'return_url' => $config['returnUrl'],
            'name' => (string) $order->subject,
            'money' => $this->money2($order->amount),
            'sitename' => $config['sitename'],
        ];
        $params['sign'] = $this->sign($params, $config['key']);
        $params['sign_type'] = 'MD5';

        return $config['gatewayUrl'].'/submit.php?'.http_build_query($params);
    }

    public function snapshot(): array
    {
        $config = $this->config();

        return [
            'pid' => $config['pid'],
            'gatewayUrl' => $config['gatewayUrl'],
            'notifyUrl' => $config['notifyUrl'],
            'returnUrl' => $config['returnUrl'],
            'displayName' => $config['displayName'],
            'sitename' => $config['sitename'],
        ];
    }

    public function verify(array $payload): bool
    {
        $config = $this->config();
        $sign = trim((string) ($payload['sign'] ?? ''));
        if ($sign === '' || (string) ($payload['pid'] ?? '') !== $config['pid'] || $config['key'] === '') {
            return false;
        }

        return hash_equals(strtolower($this->sign($payload, $config['key'])), strtolower($sign));
    }

    public function sign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);

        $params = array_filter($params, fn ($value): bool => $value !== '' && $value !== null);
        ksort($params);

        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = $name.'='.$value;
        }

        return md5(implode('&', $parts).$key);
    }

    private function money2(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
