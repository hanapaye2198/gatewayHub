<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * No-op if merchant_id was already replaced by user_id in migration 000006.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('merchant_gateways', 'merchant_id')) {
            return;
        }

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->dropUnique(['merchant_id', 'gateway_id']);
            $table->dropForeign(['merchant_id']);
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->renameColumn('merchant_id', 'user_id');
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'gateway_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('merchant_gateways', 'user_id')) {
            return;
        }

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'gateway_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->renameColumn('user_id', 'merchant_id');
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->unique(['merchant_id', 'gateway_id']);
        });
    }
};
