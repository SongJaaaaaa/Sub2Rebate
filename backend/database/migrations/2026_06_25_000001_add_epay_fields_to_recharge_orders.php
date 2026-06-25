<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->string('pay_method', 32)->nullable()->after('channel');
            $table->string('epay_trade_no', 64)->nullable()->unique()->after('pay_method');
            $table->decimal('epay_paid_amount', 18, 6)->nullable()->after('epay_trade_no');
            $table->decimal('sub2_balance_before', 18, 6)->nullable()->after('epay_paid_amount');
            $table->decimal('sub2_balance_after', 18, 6)->nullable()->after('sub2_balance_before');
            $table->text('notify_raw')->nullable()->after('review_remark');
        });
    }

    public function down(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->dropUnique(['epay_trade_no']);
            $table->dropColumn(['pay_method', 'epay_trade_no', 'epay_paid_amount', 'sub2_balance_before', 'sub2_balance_after', 'notify_raw']);
        });
    }
};
