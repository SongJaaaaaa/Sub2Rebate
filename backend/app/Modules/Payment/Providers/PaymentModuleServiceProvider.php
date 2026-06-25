<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Services\EpayPaymentService;
use App\Modules\Payment\Services\EpaySettlementService;
use App\Modules\Payment\Services\RechargeEventService;
use Illuminate\Support\ServiceProvider;

class PaymentModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RechargeEventService::class);
        $this->app->bind(EpayPaymentService::class);
        $this->app->bind(EpaySettlementService::class);
    }
}
