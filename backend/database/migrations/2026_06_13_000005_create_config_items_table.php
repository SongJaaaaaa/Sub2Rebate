<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_items', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('group', 40)->index();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->json('value');
            $table->string('tips', 500);
            $table->unsignedInteger('sort')->default(0)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_items');
    }
};
