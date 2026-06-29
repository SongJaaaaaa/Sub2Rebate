<?php

namespace App\Modules\Invite\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Invite\Services\InviteService;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function __construct(private readonly InviteService $invites)
    {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->invites->me($user));
    }

    public function bind(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::fail(
            ApiError::FORBIDDEN,
            '邀请关系只能在注册时通过 Sub2API 邀请链接建立',
            null,
            403
        );
    }

    public function tree(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->invites->tree($user, (int) $request->integer('maxDepth', 3)));
    }

    public function records(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if ($user === null) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        return ApiResponse::ok($this->invites->records(
            $user,
            (int) $request->integer('page', 1),
            (int) $request->integer('pageSize', 20)
        ));
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
