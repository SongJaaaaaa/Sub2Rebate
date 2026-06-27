<?php

namespace App\Modules\Payment\Services;

use App\Modules\Config\Services\ConfigService;
use App\Modules\Payment\Models\RechargeOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EpayService
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function config(): array
    {
        $legacyMode = trim((string) $this->configs->get('payment.mode', '')) === 'alimpay_qr';
        $keys = fn (string $newKey, string $oldKey): array => $legacyMode ? [$oldKey, $newKey] : [$newKey, $oldKey];
        $gatewayUrl = rtrim($this->cfg($keys('payment.epay.gateway_url', 'payment.alimpay.gateway_url'), 'EPAY_GATEWAY'), '/');
        $notifyUrl = $this->cfg($keys('payment.epay.notify_url', 'payment.alimpay.notify_url'));
        $returnUrl = $this->cfg($keys('payment.epay.return_url', 'payment.alimpay.return_url'), 'EPAY_RETURN_URL');
        $enabled = (bool) $this->configs->get('payment.epay.enabled', false);
        if (! $enabled) {
            $enabled = (bool) $this->configs->get('payment.alimpay.enabled', false);
        }

        return [
            'enabled' => $enabled,
            'pid' => $this->cfg($keys('payment.epay.pid', 'payment.alimpay.pid'), 'EPAY_PID'),
            'key' => $this->cfg($keys('payment.epay.key', 'payment.alimpay.key'), 'EPAY_KEY'),
            'type' => $this->cfg($keys('payment.epay.type', 'payment.alimpay.type'), 'EPAY_TYPE', 'alipay') ?: 'alipay',
            'gatewayUrl' => $gatewayUrl,
            'notifyUrl' => $notifyUrl !== '' ? $notifyUrl : url('/api/v1/recharge/epay/notify'),
            'returnUrl' => $returnUrl !== '' ? $returnUrl : $this->frontendUrl().'/recharge',
            'displayName' => $this->cfg($keys('payment.epay.display_name', 'payment.alimpay.display_name'), default: 'Epay 当面付'),
            'sitename' => $this->cfg($keys('payment.epay.sitename', 'payment.alimpay.sitename'), default: 'Sub2Rebate'),
        ];
    }

    public function canCreate(): ?string
    {
        $config = $this->config();
        if (! $config['enabled']) {
            return 'Epay 通道未启用';
        }

        foreach (['pid' => '商户 PID', 'key' => '商户 Key', 'gatewayUrl' => 'Epay 地址', 'notifyUrl' => '回调地址', 'returnUrl' => '返回地址'] as $field => $name) {
            if ($config[$field] === '') {
                return 'Epay '.$name.'未配置';
            }
        }

        return null;
    }

    public function createOrder(RechargeOrder $order, string $clientIp): array
    {
        $config = $this->config();
        $params = [
            'pid' => $config['pid'],
            'type' => $config['type'],
            'out_trade_no' => (string) $order->out_trade_no,
            'notify_url' => $config['notifyUrl'],
            'return_url' => $config['returnUrl'],
            'name' => (string) $order->subject,
            'money' => $this->money2($order->amount),
            'clientip' => $clientIp,
            'device' => 'pc',
            'method' => 'jump',
            'sitename' => $config['sitename'],
            'timestamp' => (string) time(),
        ];
        $params['sign'] = $this->sign($params, $config['key']);
        $params['sign_type'] = 'MD5';

        $res = Http::asForm()
            ->timeout(15)
            ->post($config['gatewayUrl'].'/mapi.php', $params);

        if (! $res->ok()) {
            throw new RuntimeException('Epay 下单失败：HTTP '.$res->status());
        }

        $body = $res->json();
        if (! is_array($body)) {
            throw new RuntimeException('Epay 下单失败：返回格式错误');
        }

        if ((int) ($body['code'] ?? -1) !== 1) {
            throw new RuntimeException('Epay 下单失败：'.(string) ($body['msg'] ?? '未知错误'));
        }

        $payUrl = (string) ($body['payurl'] ?? $body['url'] ?? $body['pay_info'] ?? '');
        $qrUrl = (string) ($body['qrcode'] ?? '');
        if ($payUrl === '' && $qrUrl !== '') {
            $payUrl = $qrUrl;
        }

        return [
            'payUrl' => $payUrl,
            'qrUrl' => $qrUrl,
            'tradeNo' => (string) ($body['trade_no'] ?? ''),
            'raw' => $body,
        ];
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
            'type' => $config['type'],
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

        $params = array_filter($params, fn ($value): bool => $value !== '' && $value !== null && ! is_array($value));
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

    private function frontendUrl(): string
    {
        return rtrim(trim((string) config('app.frontend_url', config('app.url'))), '/');
    }

    private function cfg(string|array $keys, string $env = '', string $default = ''): string
    {
        foreach ((array) $keys as $key) {
            $value = trim((string) $this->configs->get($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $env !== '' ? trim((string) env($env, $default)) : $default;
    }
}
