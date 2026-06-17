<?php

namespace App\Modules\Withdraw\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Withdraw\Services\WithdrawService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawController extends Controller
{
    public function __construct(private readonly WithdrawService $withdraw)
    {
    }

    public function config(): JsonResponse
    {
        return ApiResponse::ok($this->withdraw->config());
    }

    public function account(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok([
            'account' => $this->withdraw->account($user),
        ]);
    }

    public function saveAccount(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $result = $this->withdraw->saveAccount($user, $request->all());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok([
            'account' => $result['account'],
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $result = $this->withdraw->apply($user, $request->all());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::fail((int) $result['code'], (string) $result['message'], null, (int) $result['status']);
        }

        return ApiResponse::ok([
            'record' => $result['record'],
            'balance' => $result['balance'],
        ]);
    }

    public function records(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->withdraw->records(
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
