<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub2api_upstream_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('sub2api_id', 120)->unique();
            $table->string('name')->nullable()->index();
            $table->string('provider', 80)->nullable()->index();
            $table->string('model', 120)->nullable();
            $table->string('status', 60)->nullable()->index();
            $table->decimal('used_quota', 18, 6)->default(0);
            $table->decimal('total_quota', 18, 6)->default(0);
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->string('last_error', 1000)->nullable();
            $table->json('raw_account')->nullable();
            $table->json('raw_usage')->nullable();
            $table->json('raw_stats')->nullable();
            $table->json('raw_today_stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub2api_upstream_accounts');
    }
};
