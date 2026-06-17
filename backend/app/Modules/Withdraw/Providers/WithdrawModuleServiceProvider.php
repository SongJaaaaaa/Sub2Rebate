<?php

namespace App\Modules\Withdraw\Providers;

use App\Modules\Withdraw\Services\WithdrawService;
use Illuminate\Support\ServiceProvider;

class WithdrawModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WithdrawService::class);
    }
}
