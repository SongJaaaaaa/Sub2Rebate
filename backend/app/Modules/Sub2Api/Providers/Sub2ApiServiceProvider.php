<?php

namespace App\Modules\Sub2Api\Providers;

use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Modules\Sub2Api\Services\Sub2ApiUpstreamAccountSyncService;
use Illuminate\Support\ServiceProvider;

class Sub2ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Sub2ApiUserRepository::class);
        $this->app->singleton(Sub2ApiAdminClient::class);
        $this->app->singleton(Sub2ApiUpstreamAccountSyncService::class);
    }
}
