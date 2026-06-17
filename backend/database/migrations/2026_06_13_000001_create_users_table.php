<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('username')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('role', 20)->default('user')->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('sub2api_aff_code', 32)->nullable()->index();
            $table->unsignedBigInteger('sub2api_inviter_id')->nullable()->index();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
