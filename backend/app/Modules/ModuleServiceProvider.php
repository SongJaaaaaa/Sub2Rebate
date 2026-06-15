<?php

namespace App\Modules;

use App\Modules\Audit\Providers\AuditModuleServiceProvider;
use App\Modules\Admin\Providers\AdminModuleServiceProvider;
use App\Modules\Auth\Providers\AuthModuleServiceProvider;
use App\Modules\Config\Providers\ConfigModuleServiceProvider;
use App\Modules\Invite\Providers\InviteModuleServiceProvider;
use App\Modules\Milestone\Providers\MilestoneModuleServiceProvider;
use App\Modules\Payment\Providers\PaymentModuleServiceProvider;
use App\Modules\Rebate\Providers\RebateModuleServiceProvider;
use App\Modules\Risk\Providers\RiskModuleServiceProvider;
use App\Modules\Sub2Api\Providers\Sub2ApiServiceProvider;
use App\Modules\User\Providers\UserModuleServiceProvider;
use App\Modules\Withdraw\Providers\WithdrawModuleServiceProvider;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string<ServiceProvider>>
     */
    private array $providers = [
        Sub2ApiServiceProvider::class,
        AuditModuleServiceProvider::class,
        RiskModuleServiceProvider::class,
        AdminModuleServiceProvider::class,
        AuthModuleServiceProvider::class,
        ConfigModuleServiceProvider::class,
        InviteModuleServiceProvider::class,
        PaymentModuleServiceProvider::class,
        RebateModuleServiceProvider::class,
        MilestoneModuleServiceProvider::class,
        UserModuleServiceProvider::class,
        WithdrawModuleServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }
}
