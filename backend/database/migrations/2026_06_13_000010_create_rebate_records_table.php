<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('payer_user_id')->index();
            $table->unsignedBigInteger('receiver_user_id')->index();
            $table->string('type', 40)->index();
            $table->unsignedInteger('level')->default(1);
            $table->decimal('source_amount', 18, 6);
            $table->decimal('rebate_amount', 18, 6);
            $table->string('status', 30)->default('confirmed')->index();
            $table->json('config_snapshot');
            $table->string('remark', 500)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'receiver_user_id', 'level', 'type']);
            $table->foreign('event_id')->references('id')->on('rebate_events')->cascadeOnDelete();
            $table->foreign('payer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('receiver_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_records');
    }
};
