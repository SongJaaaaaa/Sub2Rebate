<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            if (app()->environment('testing') && ! config('sub2rebate.test_rate_limit', false)) {
                return Limit::none();
            }

            $account = mb_strtolower(trim((string) $request->input('account', '')));

            return Limit::perMinute(5)->by($account.'|'.$request->ip());
        });

        RateLimiter::for('withdraw', function (Request $request) {
            if (app()->environment('testing') && ! config('sub2rebate.test_rate_limit', false)) {
                return Limit::none();
            }

            $userId = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(3)->by((string) $userId);
        });

        RateLimiter::for('payment-notify', function (Request $request) {
            if (app()->environment('testing') && ! config('sub2rebate.test_rate_limit', false)) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by((string) $request->ip());
        });
    }
}
