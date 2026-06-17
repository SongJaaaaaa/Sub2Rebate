<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('source_type', 80);
            $table->string('source_id', 120);
            $table->string('status', 30)->default('paid')->index();
            $table->decimal('source_amount', 18, 6);
            $table->string('source_currency', 20)->default('CNY');
            $table->decimal('standard_amount', 18, 6);
            $table->string('standard_currency', 20)->default('CNY');
            $table->decimal('credit_amount', 18, 6)->default(0);
            $table->json('config_snapshot');
            $table->unsignedBigInteger('operator_user_id')->nullable()->index();
            $table->string('remark', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('operator_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
