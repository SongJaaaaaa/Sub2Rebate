<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Payment\Models\RechargeOrder;
use App\Modules\Payment\Services\RechargeOrderService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function __construct(private readonly RechargeOrderService $orders)
    {
    }

    public function config(): JsonResponse
    {
        return ApiResponse::ok($this->orders->config());
    }

    public function create(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $result = $this->orders->create($user, $request->all());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($result['order']);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $order = RechargeOrder::query()->find($id);
        if (! $order instanceof RechargeOrder) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '充值订单不存在', null, 404);
        }

        $result = $this->orders->submit($user, $order, $request->all());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($result['order']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $order = RechargeOrder::query()->find($id);
        if (! $order instanceof RechargeOrder) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '充值订单不存在', null, 404);
        }

        $result = $this->orders->show($user, $order);
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok($result['order']);
    }

    public function records(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->orders->list(
            $user,
            (int) $request->integer('page', 1),
            (int) $request->integer('pageSize', 20),
            trim((string) $request->query('status', ''))
        ));
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
