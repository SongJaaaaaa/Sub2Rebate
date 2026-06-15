<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin' || $user->status !== 'active') {
            return ApiResponse::fail(ApiError::FORBIDDEN, '需要管理员权限', null, 403);
        }

        return $next($request);
    }
}
