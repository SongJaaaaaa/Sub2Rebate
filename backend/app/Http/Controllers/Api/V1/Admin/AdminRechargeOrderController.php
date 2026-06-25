<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\AdminRechargeOrderService;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Services\RechargeCallbackService;
use App\Modules\Payment\Services\RechargeOrderService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRechargeOrderController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(
        private readonly AdminRechargeOrderService $adminOrders,
        private readonly RechargeOrderService $orders,
        private readonly RechargeCallbackService $callbacks,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $status = trim($request->string('status')->toString());

        $query = RechargeOrder::query();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (RechargeOrder $row): array => $this->recordPayload($row))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->operate($request, $id, 'approve');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->operate($request, $id, 'reject');
    }

    public function retryCredit(Request $request, int $id): JsonResponse
    {
        $order = RechargeOrder::query()->find($id);
        if (! $order instanceof RechargeOrder) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '充值订单不存在', null, 404);
        }

        $result = $this->callbacks->retryCredit($order, $request->user());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($this->recordPayload($result['order']->refresh()));
    }

    private function operate(Request $request, int $id, string $method): JsonResponse
    {
        $order = RechargeOrder::query()->find($id);
        if (! $order instanceof RechargeOrder) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '充值订单不存在', null, 404);
        }

        $result = $this->adminOrders->{$method}($request->user(), $order, trim((string) $request->input('remark', '后台充值操作')));
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($this->recordPayload($result['order']));
    }

    private function recordPayload(RechargeOrder $order): array
    {
        $user = User::query()->find((int) $order->user_id);
        $base = $this->orders->payload($order);

        return $base + [
            'userId' => (int) $order->user_id,
            'username' => (string) ($user?->username ?: $user?->email ?: ''),
            'nickname' => (string) ($user?->username ?: 'user_'.$order->user_id),
        ];
    }
}
