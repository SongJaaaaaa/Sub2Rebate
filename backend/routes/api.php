<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminBalanceController;
use App\Http\Controllers\Api\V1\Admin\AdminConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminRechargeOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminRebateController;
use App\Http\Controllers\Api\V1\Admin\AdminRelationshipController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminWithdrawController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Payment\EpayNotifyController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\RechargeController;
use App\Http\Controllers\Api\V1\RebateRecordController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Config\Http\Controllers\ConfigItemController;
use App\Modules\Invite\Http\Controllers\InviteController;
use App\Modules\User\Http\Controllers\AccountController;
use App\Modules\User\Http\Controllers\AccountProfileController;
use App\Modules\Withdraw\Http\Controllers\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.v1.auth.login');

    // Epay 当面付异步通知（公开、无需登录、Laravel12 api 路由天然免 CSRF）
    Route::match(['get', 'post'], 'recharge/epay/notify', EpayNotifyController::class)
        ->middleware('throttle:60,1')->name('api.v1.recharge.epay.notify');

    Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::get('account/profile', AccountProfileController::class)->name('api.v1.account.profile');
        Route::put('account/profile', [AccountController::class, 'updateProfile'])->name('api.v1.account.profile.update');
        Route::post('account/change-password', [AccountController::class, 'changePassword'])->name('api.v1.account.change-password');
        Route::get('config/items', [ConfigItemController::class, 'index'])->name('api.v1.config.items');
        Route::get('recharge/config', [RechargeController::class, 'config'])->name('api.v1.recharge.config');
        Route::post('recharge/orders', [RechargeController::class, 'create'])->name('api.v1.recharge.orders.create');
        Route::post('recharge/orders/{id}/submit', [RechargeController::class, 'submit'])->name('api.v1.recharge.orders.submit');
        Route::post('recharge/epay/pay', [RechargeController::class, 'epayPay'])->middleware('throttle:30,1')->name('api.v1.recharge.epay.pay');
        Route::get('recharge/orders', [RechargeController::class, 'records'])->name('api.v1.recharge.orders');
        Route::get('invite/me', [InviteController::class, 'me'])->name('api.v1.invite.me');
        Route::post('invite/bind', [InviteController::class, 'bind'])->name('api.v1.invite.bind');
        Route::get('invite/tree', [InviteController::class, 'tree'])->name('api.v1.invite.tree');
        Route::get('invite/records', [InviteController::class, 'records'])->name('api.v1.invite.records');
        Route::get('withdraw/config', [WithdrawController::class, 'config'])->name('api.v1.withdraw.config');
        Route::get('withdraw/account', [WithdrawController::class, 'account'])->name('api.v1.withdraw.account');
        Route::post('withdraw/account', [WithdrawController::class, 'saveAccount'])->name('api.v1.withdraw.account.save');
        Route::post('withdraw/apply', [WithdrawController::class, 'apply'])->middleware('throttle:withdraw')->name('api.v1.withdraw.apply');
        Route::get('withdraw/records', [WithdrawController::class, 'records'])->name('api.v1.withdraw.records');
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('api.v1.dashboard.summary');
        Route::get('dashboard/rebate-trends', [DashboardController::class, 'rebateTrends'])->name('api.v1.dashboard.rebate-trends');
        Route::get('dashboard/recent-activities', [DashboardController::class, 'recentActivities'])->name('api.v1.dashboard.recent-activities');
        Route::get('rebate/records', [RebateRecordController::class, 'index'])->name('api.v1.rebate.records');
        Route::get('promotion/summary', [PromotionController::class, 'summary'])->name('api.v1.promotion.summary');
        Route::get('promotion/conversions', [PromotionController::class, 'conversions'])->name('api.v1.promotion.conversions');
        Route::get('promotion/rebate-records', [PromotionController::class, 'rebateRecords'])->name('api.v1.promotion.rebate-records');
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'active.user', 'admin'])->group(function (): void {
        Route::get('dashboard', [AdminDashboardController::class, 'dashboard'])->name('api.v1.admin.dashboard');
        Route::get('trends', [AdminDashboardController::class, 'trends'])->name('api.v1.admin.trends');
        Route::get('users', [AdminUserController::class, 'index'])->name('api.v1.admin.users');
        Route::get('recharge-orders', [AdminRechargeOrderController::class, 'index'])->name('api.v1.admin.recharge-orders');
        Route::post('recharge-orders/{id}/approve', [AdminRechargeOrderController::class, 'approve'])->name('api.v1.admin.recharge-orders.approve');
        Route::post('recharge-orders/{id}/reject', [AdminRechargeOrderController::class, 'reject'])->name('api.v1.admin.recharge-orders.reject');
        Route::post('users/{id}/ban', [AdminUserController::class, 'ban'])->name('api.v1.admin.users.ban');
        Route::post('users/{id}/unban', [AdminUserController::class, 'unban'])->name('api.v1.admin.users.unban');
        Route::post('users/{id}/role', [AdminUserController::class, 'setRole'])->name('api.v1.admin.users.role');
        Route::get('withdrawals', [AdminWithdrawController::class, 'index'])->name('api.v1.admin.withdrawals');
        Route::post('withdrawals/{id}/approve', [AdminWithdrawController::class, 'approve'])->name('api.v1.admin.withdrawals.approve');
        Route::post('withdrawals/{id}/reject', [AdminWithdrawController::class, 'reject'])->name('api.v1.admin.withdrawals.reject');
        Route::post('withdrawals/{id}/paid', [AdminWithdrawController::class, 'markPaid'])->name('api.v1.admin.withdrawals.paid');
        Route::get('rebate-config', [AdminConfigController::class, 'show'])->name('api.v1.admin.rebate-config');
        Route::put('rebate-config', [AdminConfigController::class, 'update'])->name('api.v1.admin.rebate-config.update');
        Route::get('payment-config', [AdminConfigController::class, 'payment'])->name('api.v1.admin.payment-config');
        Route::put('payment-config', [AdminConfigController::class, 'updatePayment'])->name('api.v1.admin.payment-config.update');
        Route::post('balance-adjust', [AdminBalanceController::class, 'adjust'])->name('api.v1.admin.balance-adjust');
        Route::get('users/{id}/api-quota', [AdminBalanceController::class, 'apiQuota'])->name('api.v1.admin.users.api-quota.show');
        Route::post('users/{id}/api-quota', [AdminBalanceController::class, 'adjustApiQuota'])->name('api.v1.admin.users.api-quota');
        Route::get('users/{id}/api-quota-records', [AdminBalanceController::class, 'apiQuotaRecords'])->name('api.v1.admin.users.api-quota-records');
        Route::get('users/{id}/balance-records', [AdminBalanceController::class, 'records'])->name('api.v1.admin.users.balance-records');
        Route::get('relationship-tree', [AdminRelationshipController::class, 'tree'])->name('api.v1.admin.relationship-tree');
        Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('api.v1.admin.audit-logs');
        Route::get('user-rebate-overrides', [AdminRebateController::class, 'overrides'])->name('api.v1.admin.user-rebate-overrides');
        Route::get('users/{id}/rebate-override', [AdminRebateController::class, 'showOverride'])->name('api.v1.admin.users.rebate-override');
        Route::put('users/{id}/rebate-override', [AdminRebateController::class, 'updateOverride'])->name('api.v1.admin.users.rebate-override.update');
    });
});
