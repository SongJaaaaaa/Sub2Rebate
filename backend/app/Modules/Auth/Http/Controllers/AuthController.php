<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\Sub2RebateAuthService;
use App\Modules\User\Services\AccountProfileService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly Sub2RebateAuthService $auth,
        private readonly AccountProfileService $profiles,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $account = trim((string) $request->input('account'));
        $password = (string) $request->input('password');

        if ($account === '' || $password === '') {
            return ApiResponse::fail(ApiError::BAD_REQUEST, '账号和密码不能为空');
        }

        $result = $this->auth->attempt($account, $password);

        if ($result === null) {
            return ApiResponse::fail(ApiError::LOGIN_FAILED, '账号或密码错误', null, 401);
        }

        if (($result['error'] ?? null) === 'disabled') {
            return ApiResponse::fail(ApiError::USER_DISABLED, '账号已被禁用', null, 403);
        }

        /** @var User $user */
        $user = $result['user'];

        return ApiResponse::ok([
            'token' => $result['token'],
            'tokenType' => $result['tokenType'],
            'user' => $this->profiles->userPayload($user, $result['sub2User']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return ApiResponse::ok(null);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->profiles->me($user));
    }
}
