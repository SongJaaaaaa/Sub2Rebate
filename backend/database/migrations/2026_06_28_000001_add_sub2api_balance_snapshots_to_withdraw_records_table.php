<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->decimal('sub2api_balance_before', 18, 6)->nullable()->after('real_name');
            $table->decimal('sub2api_balance_after', 18, 6)->nullable()->after('sub2api_balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_records', function (Blueprint $table): void {
            $table->dropColumn(['sub2api_balance_before', 'sub2api_balance_after']);
        });
    }
};
