<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('withdraw_account_id')->nullable()->index();
            $table->decimal('amount', 18, 6);
            $table->string('status', 30)->default('pending')->index();
            $table->string('account_type', 30);
            $table->string('account_no', 160);
            $table->string('real_name', 80);
            $table->string('remark', 500)->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('withdraw_account_id')->references('id')->on('withdraw_accounts')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_records');
    }
};
