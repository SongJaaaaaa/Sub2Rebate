<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User && $user->status !== 'active') {
            $token = $user->currentAccessToken();
            if ($token !== null) {
                $token->delete();
            }

            return ApiResponse::fail(ApiError::USER_DISABLED, '账号已被禁用', null, 403);
        }

        return $next($request);
    }
}
