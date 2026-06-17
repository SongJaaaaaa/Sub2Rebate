<?php

namespace App\Modules\Admin\Providers;

use App\Modules\Admin\Services\AdminAccessService;
use App\Modules\Admin\Services\AdminWithdrawService;
use Illuminate\Support\ServiceProvider;

class AdminModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminAccessService::class);
        $this->app->singleton(AdminWithdrawService::class);
    }
}
