<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->unique('api_key_hash');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index('status');
            $table->index(['merchant_id', 'created_at']);
            $table->index(['merchant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['merchant_id', 'status']);
            $table->dropIndex(['merchant_id', 'created_at']);
            $table->dropIndex(['status']);
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropUnique(['api_key_hash']);
        });
    }
};
