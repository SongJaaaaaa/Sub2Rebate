<?php

namespace App\Modules\Rebate\Providers;

use App\Modules\Rebate\Services\DecayRebateCalculator;
use App\Modules\Rebate\Services\DecayRebateService;
use App\Modules\Rebate\Services\RebateBalanceService;
use Illuminate\Support\ServiceProvider;

class RebateModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DecayRebateCalculator::class);
        $this->app->bind(DecayRebateService::class);
        $this->app->singleton(RebateBalanceService::class);
    }
}
