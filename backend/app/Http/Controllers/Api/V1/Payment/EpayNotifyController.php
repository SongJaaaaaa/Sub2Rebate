<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Services\EpaySettlementService;
use App\Modules\Payment\Support\EpayGatewayConfig;
use App\Modules\Payment\Support\EpaySignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Epay（彩虹易支付）当面付异步通知入口。
 *
 * 独立验签，绝不复用支付宝官方 RSA 回调入口。
 * 校验顺序：验签 → pid → trade_status → 查单/归属/通道 → 金额 → 幂等 → 结算。
 * 成功且仅成功时输出纯文本 success（否则 Epay 会按策略重发）。
 */
class EpayNotifyController extends Controller
{
    public function __construct(
        private readonly EpayGatewayConfig $gateway,
        private readonly EpaySettlementService $settlement,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $params = $request->all();

        // 1. 验签（hash_equals）
        if (! EpaySignature::verify($params, $this->gateway->key())) {
            return $this->fail('sign', $params);
        }

        // 2. pid 必须是当前配置商户
        if ((string) ($params['pid'] ?? '') !== $this->gateway->pid()) {
            return $this->fail('pid', $params);
        }

        // 3. 交易状态
        if ((string) ($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return $this->fail('trade_status', $params);
        }

        // 4. 按 out_trade_no 查本地订单，校验通道
        $outTradeNo = (string) ($params['out_trade_no'] ?? '');
        $order = RechargeOrder::query()->where('order_no', $outTradeNo)->first();
        if (! $order instanceof RechargeOrder || $order->channel !== 'epay') {
            return $this->fail('order', $params);
        }

        // 5. 幂等短路：已入账直接回 success
        if ($order->status === RechargeOrder::STATUS_PAID) {
            return $this->ok();
        }

        // 只处理待支付订单（避免对 expired/其它状态误入账）
        if ($order->status !== RechargeOrder::STATUS_PENDING) {
            return $this->fail('status', $params);
        }

        // 6. 金额校验：实付不得小于订单金额（用分比较，避免浮点误差）
        $paid = (int) round(((float) ($params['money'] ?? 0)) * 100);
        $expect = (int) round(((float) $order->amount) * 100);
        if ($paid < $expect) {
            return $this->fail('amount', $params);
        }

        // 7. 结算（Sub2API 加余额 + 返利事件 + 更新订单，内部幂等）
        $result = $this->settlement->settle(
            $order,
            (string) ($params['trade_no'] ?? ''),
            number_format(((float) ($params['money'] ?? 0)), 6, '.', ''),
            (string) ($params['type'] ?? $this->gateway->type()),
            $this->sanitize($params),
        );

        if (! ($result['ok'] ?? false)) {
            Log::warning('epay.notify.settle_failed', [
                'order' => $outTradeNo,
                'message' => $result['message'] ?? '',
            ]);

            return new Response('fail', 200);
        }

        return $this->ok();
    }

    private function ok(): Response
    {
        return new Response('success', 200);
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function fail(string $reason, array $params): Response
    {
        Log::warning('epay.notify.rejected', [
            'reason' => $reason,
            'out_trade_no' => $params['out_trade_no'] ?? null,
            'pid' => $params['pid'] ?? null,
        ]);

        return new Response('fail', 200);
    }

    /**
     * 留痕用：去掉签名等敏感字段。
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function sanitize(array $params): array
    {
        unset($params['sign']);

        return $params;
    }
}
