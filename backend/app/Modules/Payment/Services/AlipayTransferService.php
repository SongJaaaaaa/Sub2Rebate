<?php

namespace App\Modules\Payment\Services;

use App\Modules\Config\Services\ConfigService;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlipayTransferService
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function enabled(): bool
    {
        return $this->bool($this->configs->get('payment.alipay_transfer.enabled', false));
    }

    public function autoPayEnabled(): bool
    {
        return $this->enabled() && $this->bool($this->configs->get('payment.alipay_transfer.auto_pay_enabled', false));
    }

    public function retryEnabled(): bool
    {
        return $this->enabled() && $this->bool($this->configs->get('payment.alipay_transfer.retry_enabled', false));
    }

    public function retryIntervalMinutes(): int
    {
        return max(1, (int) $this->configs->get('payment.alipay_transfer.retry_interval_minutes', 5));
    }

    public function retryBatchSize(): int
    {
        return max(1, min((int) $this->configs->get('payment.alipay_transfer.retry_batch_size', 50), 500));
    }

    public function config(): array
    {
        return [
            'enabled' => $this->enabled(),
            'gatewayUrl' => rtrim($this->cfg('payment.alipay_transfer.gateway_url', 'ALIPAY_TRANSFER_GATEWAY', 'https://openapi.alipay.com/gateway.do'), '/'),
            'appId' => $this->cfg('payment.alipay_transfer.app_id', 'ALIPAY_TRANSFER_APP_ID'),
            'privateKey' => $this->cfg('payment.alipay_transfer.private_key', 'ALIPAY_TRANSFER_PRIVATE_KEY'),
            'alipayPublicKey' => $this->cfg('payment.alipay_transfer.alipay_public_key', 'ALIPAY_TRANSFER_PUBLIC_KEY'),
            'singleMaxAmount' => (float) $this->configs->get('payment.alipay_transfer.single_max_amount', '500'),
            'dailyLimitAmount' => (float) $this->configs->get('payment.alipay_transfer.daily_limit_amount', '5000'),
            'identityType' => $this->cfg('payment.alipay_transfer.identity_type', 'ALIPAY_TRANSFER_IDENTITY_TYPE', 'ALIPAY_LOGON_ID') ?: 'ALIPAY_LOGON_ID',
            'orderTitle' => trim((string) $this->configs->get('payment.alipay_transfer.order_title', '返利提现')) ?: '返利提现',
            'autoPayEnabled' => $this->autoPayEnabled(),
            'retryEnabled' => $this->retryEnabled(),
            'retryIntervalMinutes' => $this->retryIntervalMinutes(),
            'retryBatchSize' => $this->retryBatchSize(),
        ];
    }

    public function transfer(WithdrawRecord $record): array
    {
        $config = $this->config();
        $this->assertReady($record, $config);

        $outBizNo = $this->outBizNo($record);
        $biz = [
            'out_biz_no' => $outBizNo,
            'trans_amount' => $this->money2($record->amount),
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'biz_scene' => 'DIRECT_TRANSFER',
            'order_title' => $config['orderTitle'],
            'payee_info' => [
                'identity' => (string) $record->account_no,
                'identity_type' => $config['identityType'],
                'name' => (string) $record->real_name,
            ],
        ];

        $body = $this->request('alipay.fund.trans.uni.transfer', $biz, $config);
        $node = $body['alipay_fund_trans_uni_transfer_response'] ?? $body;
        if ((string) ($node['code'] ?? '') !== '10000') {
            throw new RuntimeException('支付宝返回失败：'.(string) ($node['sub_msg'] ?? $node['msg'] ?? '未知错误'));
        }

        return [
            'outBizNo' => $outBizNo,
            'tradeNo' => (string) ($node['order_id'] ?? $node['pay_fund_order_id'] ?? $outBizNo),
            'raw' => $body,
        ];
    }

    public function query(string $outBizNo): array
    {
        $config = $this->config();
        $this->assertConfig($config);

        return $this->request('alipay.fund.trans.common.query', [
            'out_biz_no' => $outBizNo,
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
        ], $config);
    }

    private function request(string $method, array $biz, array $config): array
    {
        $params = [
            'app_id' => $config['appId'],
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $params['sign'] = $this->sign($params, $config['privateKey']);

        $res = Http::asForm()
            ->timeout(15)
            ->post($config['gatewayUrl'], $params);

        if (! $res->ok()) {
            throw new RuntimeException('支付宝接口请求失败：HTTP '.$res->status());
        }

        $body = $res->json();
        if (! is_array($body)) {
            throw new RuntimeException('支付宝接口返回格式错误');
        }

        return $body;
    }

    private function assertReady(WithdrawRecord $record, array $config): void
    {
        $this->assertConfig($config);

        if ((string) $record->account_no === '' || (string) $record->real_name === '') {
            throw new RuntimeException('收款支付宝账号和姓名不能为空');
        }

        $amount = (float) $record->amount;
        if ($config['singleMaxAmount'] > 0 && $amount > $config['singleMaxAmount']) {
            throw new RuntimeException('超过支付宝单笔打款限额');
        }

        if ($config['dailyLimitAmount'] > 0) {
            $paid = (float) WithdrawRecord::query()
                ->where('type', WithdrawRecord::TYPE_ALIPAY)
                ->where('status', WithdrawRecord::STATUS_PAID)
                ->whereNotNull('payout_trade_no')
                ->whereDate('payout_time', now()->toDateString())
                ->sum('amount');

            if ($paid + $amount > $config['dailyLimitAmount']) {
                throw new RuntimeException('超过支付宝单日打款限额');
            }
        }
    }

    private function assertConfig(array $config): void
    {
        foreach ([
            'appId' => '应用 ID',
            'privateKey' => '应用私钥',
            'gatewayUrl' => '网关地址',
        ] as $field => $name) {
            if (trim((string) $config[$field]) === '') {
                throw new RuntimeException('支付宝转账'.$name.'未配置');
            }
        }
    }

    private function sign(array $params, string $privateKey): string
    {
        unset($params['sign']);
        ksort($params);

        $parts = [];
        foreach ($params as $name => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $name.'='.$value;
            }
        }

        $key = $this->normalizePrivateKey($privateKey);
        $resource = openssl_pkey_get_private($key);
        if ($resource === false) {
            throw new RuntimeException('支付宝应用私钥格式错误');
        }

        $ok = openssl_sign(implode('&', $parts), $signature, $resource, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('支付宝请求签名失败');
        }

        return base64_encode($signature);
    }

    private function normalizePrivateKey(string $key): string
    {
        $key = trim(str_replace('\n', "\n", $key));
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        return "-----BEGIN PRIVATE KEY-----\n".chunk_split($key, 64, "\n")."-----END PRIVATE KEY-----";
    }

    private function outBizNo(WithdrawRecord $record): string
    {
        return 'SRWD'.$record->id;
    }

    private function money2(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function cfg(string $key, string $env = '', string $default = ''): string
    {
        $value = trim((string) $this->configs->get($key, ''));
        if ($value !== '') {
            return $value;
        }

        return $env !== '' ? trim((string) env($env, $default)) : $default;
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
