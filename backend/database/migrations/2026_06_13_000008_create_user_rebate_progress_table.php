<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_rebate_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('total_recharge_amount', 18, 6)->default(0);
            $table->unsignedInteger('milestone_times')->default(0);
            $table->unsignedBigInteger('last_event_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('last_event_id')->references('id')->on('rebate_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rebate_progress');
    }
};
