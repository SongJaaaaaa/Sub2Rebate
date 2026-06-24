<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('rebate:process-pending --limit=100')->everyMinute()->withoutOverlapping();
        $schedule->command('rebate:check-lie-flat-users --limit=500')->dailyAt('03:20')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'active.user' => EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return ApiResponse::fail(ApiError::VALIDATION_FAILED, '参数格式错误', $e->errors(), 422);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                if ($e instanceof NotFoundHttpException) {
                    return ApiResponse::fail(ApiError::NOT_FOUND, '资源不存在', null, 404);
                }

                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    $code = $status === 403 ? ApiError::FORBIDDEN : ApiError::BAD_REQUEST;

                    return ApiResponse::fail($code, $e->getMessage() ?: '请求失败', null, $status);
                }

                report($e);

                return ApiResponse::fail(ApiError::SERVER_ERROR, '服务端异常', null, 500);
            }

            return null;
        });
    })->create();
