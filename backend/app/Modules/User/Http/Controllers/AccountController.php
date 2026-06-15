<?php

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * POST /api/v1/account/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::fail(
            ApiError::BAD_REQUEST,
            '登录密码由 Sub2API 统一管理，请前往 Sub2API 修改密码'
        );
    }

    /**
     * PUT /api/v1/account/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        $request->validate([
            'nickname' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:100|unique:users,email,'.$user->id,
        ]);

        $fields = [];
        if ($request->has('nickname')) {
            $fields['username'] = $request->input('nickname');
        }
        if ($request->has('email')) {
            $fields['email'] = $request->input('email');
        }

        if (! empty($fields)) {
            $user->forceFill($fields)->save();
        }

        return ApiResponse::ok(null, '资料更新成功');
    }
}
