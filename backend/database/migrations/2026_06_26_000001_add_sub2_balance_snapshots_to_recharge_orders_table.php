<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->decimal('sub2_balance_before', 18, 6)->nullable()->after('paid_amount');
            $table->decimal('sub2_balance_after', 18, 6)->nullable()->after('sub2_balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'sub2_balance_before',
                'sub2_balance_after',
            ]);
        });
    }
};
