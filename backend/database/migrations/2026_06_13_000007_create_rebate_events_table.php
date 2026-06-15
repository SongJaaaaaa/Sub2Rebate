<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('payment_record_id')->nullable()->index();
            $table->string('source_type', 80);
            $table->string('source_id', 120);
            $table->string('event_type', 40)->default('recharge')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('source_amount', 18, 6);
            $table->string('source_currency', 20)->default('CNY');
            $table->decimal('standard_amount', 18, 6);
            $table->string('standard_currency', 20)->default('CNY');
            $table->decimal('credit_amount', 18, 6)->default(0);
            $table->json('config_snapshot');
            $table->unsignedBigInteger('operator_user_id')->nullable()->index();
            $table->string('remark', 500)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('payment_record_id')->references('id')->on('payment_records')->nullOnDelete();
            $table->foreign('operator_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_events');
    }
};
