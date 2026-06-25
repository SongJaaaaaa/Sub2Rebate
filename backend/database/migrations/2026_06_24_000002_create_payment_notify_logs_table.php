<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notify_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30)->index();
            $table->string('event_type', 30)->default('trade_notify')->index();
            $table->string('out_trade_no', 64)->nullable()->index();
            $table->string('provider_trade_no', 64)->nullable()->index();
            $table->string('notify_id', 64)->nullable()->index();
            $table->string('trade_status', 40)->nullable()->index();
            $table->boolean('verify_passed')->default(false);
            $table->string('handle_status', 20)->default('pending')->index();
            $table->string('handle_msg', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notify_logs');
    }
};
