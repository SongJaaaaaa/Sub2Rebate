<?php

namespace App\Modules\Config\Providers;

use App\Modules\Config\Services\ConfigService;
use Illuminate\Support\ServiceProvider;

class ConfigModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigService::class);
    }
}
