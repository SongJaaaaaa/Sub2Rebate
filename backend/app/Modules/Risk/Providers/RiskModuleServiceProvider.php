<?php

namespace App\Modules\Risk\Providers;

use App\Modules\Risk\Services\RiskService;
use Illuminate\Support\ServiceProvider;

class RiskModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RiskService::class);
    }
}
