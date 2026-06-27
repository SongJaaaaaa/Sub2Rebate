<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->string('payout_trade_no', 120)->nullable()->index();
            $table->text('payout_error')->nullable();
            $table->timestamp('payout_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropIndex(['payout_trade_no']);
            $table->dropColumn(['payout_trade_no', 'payout_error', 'payout_time']);
        });
    }
};
