<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->string('type', 30)->default('alipay')->index();
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
