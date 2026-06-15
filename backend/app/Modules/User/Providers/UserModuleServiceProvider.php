<?php

namespace App\Modules\User\Providers;

use App\Modules\User\Services\AccountProfileService;
use Illuminate\Support\ServiceProvider;

class UserModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountProfileService::class);
    }
}
