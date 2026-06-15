<?php

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Services\AuditLogService;
use Illuminate\Support\ServiceProvider;

class AuditModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class);
    }
}
