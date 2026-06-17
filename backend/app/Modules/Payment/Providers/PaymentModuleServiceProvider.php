<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Services\RechargeEventService;
use Illuminate\Support\ServiceProvider;

class PaymentModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RechargeEventService::class);
    }
}
