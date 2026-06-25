<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->string('out_trade_no', 64)->nullable()->unique()->after('channel');
            $table->string('provider_trade_no', 64)->nullable()->index()->after('out_trade_no');
            $table->string('subject', 120)->nullable()->after('provider_trade_no');
            $table->string('trade_status', 40)->nullable()->index()->after('status');
            $table->string('credit_status', 20)->default('pending')->index()->after('trade_status');
            $table->decimal('paid_amount', 18, 6)->nullable()->after('credit_amount');
            $table->timestamp('credited_at')->nullable()->after('paid_at');
            $table->text('pay_url')->nullable()->after('remark');
            $table->json('channel_config_snapshot')->nullable()->after('pay_url');
            $table->json('notify_payload')->nullable()->after('channel_config_snapshot');
            $table->string('credit_fail_msg', 500)->nullable()->after('notify_payload');
        });
    }

    public function down(): void
    {
        Schema::table('recharge_orders', function (Blueprint $table): void {
            $table->dropUnique(['out_trade_no']);
            $table->dropIndex(['provider_trade_no']);
            $table->dropIndex(['trade_status']);
            $table->dropIndex(['credit_status']);
            $table->dropColumn([
                'out_trade_no',
                'provider_trade_no',
                'subject',
                'trade_status',
                'credit_status',
                'paid_amount',
                'credited_at',
                'pay_url',
                'channel_config_snapshot',
                'notify_payload',
                'credit_fail_msg',
            ]);
        });
    }
};
