<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Modules\Payment\Models\PaymentNotifyLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentNotifyLogController extends Controller
{
    use FormatsApiPayloads;

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $outTradeNo = trim($request->string('outTradeNo')->toString());

        $query = PaymentNotifyLog::query();
        if ($outTradeNo !== '') {
            $query->where('out_trade_no', $outTradeNo);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (PaymentNotifyLog $row): array => [
                'id' => (int) $row->id,
                'provider' => (string) $row->provider,
                'eventType' => (string) $row->event_type,
                'outTradeNo' => (string) ($row->out_trade_no ?? ''),
                'providerTradeNo' => (string) ($row->provider_trade_no ?? ''),
                'notifyId' => (string) ($row->notify_id ?? ''),
                'tradeStatus' => (string) ($row->trade_status ?? ''),
                'verifyPassed' => (bool) $row->verify_passed,
                'handleStatus' => (string) $row->handle_status,
                'handleMsg' => (string) ($row->handle_msg ?? ''),
                'receivedAt' => $this->time($row->received_at),
                'handledAt' => $this->time($row->handled_at),
                'createdAt' => $this->time($row->created_at),
            ])->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }
}
