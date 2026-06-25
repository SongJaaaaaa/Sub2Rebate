<?php

namespace App\Modules\Payment\Services;

use App\Models\User;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Support\EpayGatewayConfig;
use App\Modules\Payment\Support\EpaySignature;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Support\ApiError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Epay 当面付下单服务。
 *
 * 流程：复用 RechargeOrderService 创建 channel='epay' 的待支付订单
 *  → 按易支付协议拼参数 + EpaySignature 签名
 *  → 请求 Epay mapi.php（API 下单）
 *  → 返回前端用于渲染/跳转的 payInfo。
 *
 * 入账不在此处——由 EpayNotifyController 收到 Epay 异步通知后完成。
 */
class EpayPaymentService
{
    public function __construct(
        private readonly RechargeOrderService $orders,
        private readonly EpayGatewayConfig $gateway,
        private readonly Sub2ApiAdminClient $sub2Api,
    ) {
    }

    /**
     * 创建 Epay 订单并向网关下单，返回支付信息。
     *
     * @return array{ok:bool, order?:array, payType?:string, payInfo?:string, code?:int, message?:string, status?:int}
     */
    public function createAndPay(User $user, array $data): array
    {
        if (! $this->gateway->enabled()) {
            return $this->fail('当前未开启在线支付');
        }

        $create = $this->orders->createPendingOrder($user, $data['amount'] ?? null, 'epay');
        if (! ($create['ok'] ?? false)) {
            return $create;
        }

        /** @var RechargeOrder $order */
        $order = $create['order'];

        // 记录下单时 Sub2API 历史余额（best-effort，查失败不阻断下单）
        $order->sub2_balance_before = $this->currentSub2Balance($order->user_id);
        if ($order->sub2_balance_before !== null) {
            $order->save();
        }

        $params = $this->buildParams($order);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($this->gateway->mapiUrl(), $params);
        } catch (\Throwable $e) {
            Log::warning('epay.mapi.request_failed', ['order' => $order->order_no, 'error' => $e->getMessage()]);

            return $this->fail('支付网关请求失败，请稍后重试');
        }

        $json = $response->json();
        if (! is_array($json) || (int) ($json['code'] ?? -1) !== 0) {
            $msg = is_array($json) ? (string) ($json['msg'] ?? '下单失败') : '下单返回异常';
            Log::warning('epay.mapi.create_failed', ['order' => $order->order_no, 'msg' => $msg]);

            return $this->fail('在线支付下单失败：' . $msg);
        }

        return [
            'ok' => true,
            'order' => $this->orders->payload($order),
            'payType' => (string) ($json['pay_type'] ?? ''),   // qrcode / jump / urlscheme ...
            'payInfo' => (string) ($json['pay_info'] ?? ''),   // 二维码内容或跳转 URL
        ];
    }

    /**
     * 构造易支付下单参数并签名。out_trade_no 直接用订单号。
     *
     * @return array<string,string>
     */
    private function buildParams(RechargeOrder $order): array
    {
        $params = [
            'pid' => $this->gateway->pid(),
            'type' => $this->gateway->type(),
            'out_trade_no' => $order->order_no,
            'notify_url' => $this->gateway->notifyUrl(),
            'return_url' => $this->gateway->returnUrl(),
            'name' => '账户充值-' . $order->order_no,
            'money' => $this->money($order->amount),
        ];

        $params['sign'] = EpaySignature::make($params, $this->gateway->key());
        $params['sign_type'] = 'MD5';

        return $params;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * 查询 Sub2API 当前余额，失败返回 null（不阻断下单）。
     */
    private function currentSub2Balance(int $userId): ?string
    {
        try {
            $res = $this->sub2Api->user($userId);
            $balance = data_get($res, 'data.balance');

            return $balance === null ? null : number_format((float) $balance, 6, '.', '');
        } catch (\Throwable $e) {
            Log::warning('epay.balance_before.query_failed', ['user' => $userId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function fail(string $message): array
    {
        return [
            'ok' => false,
            'code' => ApiError::BAD_REQUEST,
            'message' => $message,
            'status' => 400,
        ];
    }
}
