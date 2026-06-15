<?php

namespace App\Modules\Milestone\Providers;

use App\Modules\Milestone\Services\MilestoneService;
use Illuminate\Support\ServiceProvider;

class MilestoneModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MilestoneService::class);
    }
}
