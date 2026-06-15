<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Services\Sub2RebateAuthService;
use Illuminate\Support\ServiceProvider;

class AuthModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Sub2RebateAuthService::class);
    }
}
