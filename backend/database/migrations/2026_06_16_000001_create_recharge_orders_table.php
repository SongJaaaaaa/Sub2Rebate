<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharge_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('order_no', 64)->unique();
            $table->string('channel', 30)->default('alipay')->index();
            $table->decimal('amount', 18, 6);
            $table->decimal('bonus_amount', 18, 6)->default(0);
            $table->decimal('credit_amount', 18, 6)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->string('payer_name', 80)->nullable();
            $table->string('payer_account', 160)->nullable();
            $table->string('voucher_image_url', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expire_at')->nullable()->index();
            $table->string('remark', 500)->nullable();
            $table->string('review_remark', 500)->nullable();
            $table->unsignedBigInteger('rebate_event_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rebate_event_id')->references('id')->on('rebate_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharge_orders');
    }
};