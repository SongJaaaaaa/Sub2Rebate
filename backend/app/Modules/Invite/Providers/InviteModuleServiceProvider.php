<?php

namespace App\Modules\Invite\Providers;

use App\Modules\Invite\Services\InviteService;
use Illuminate\Support\ServiceProvider;

class InviteModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InviteService::class);
    }
}
