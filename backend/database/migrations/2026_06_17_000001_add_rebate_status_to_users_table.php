<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('rebate_status', 20)->default('eligible')->index()->after('status');
            $table->timestamp('last_recharge_at')->nullable()->index()->after('sub2api_inviter_id');
            $table->timestamp('last_balance_decreased_at')->nullable()->after('last_recharge_at');
            $table->timestamp('last_invited_at')->nullable()->after('last_balance_decreased_at');
            $table->timestamp('rebate_disabled_at')->nullable()->after('last_invited_at');
            $table->string('rebate_disabled_reason', 40)->nullable()->index()->after('rebate_disabled_at');
            $table->decimal('last_sub2api_balance', 18, 6)->nullable()->after('rebate_disabled_reason');
            $table->decimal('last_sub2api_total_recharged', 18, 6)->nullable()->after('last_sub2api_balance');
            $table->timestamp('last_balance_checked_at')->nullable()->after('last_sub2api_total_recharged');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['rebate_status']);
            $table->dropIndex(['last_recharge_at']);
            $table->dropIndex(['rebate_disabled_reason']);
            $table->dropColumn([
                'rebate_status',
                'last_recharge_at',
                'last_balance_decreased_at',
                'last_invited_at',
                'rebate_disabled_at',
                'rebate_disabled_reason',
                'last_sub2api_balance',
                'last_sub2api_total_recharged',
                'last_balance_checked_at',
            ]);
        });
    }
};
