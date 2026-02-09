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
        if (Schema::hasColumn('merchant_gateways', 'merchant_id')) {
            Schema::table('merchant_gateways', function (Blueprint $table) {
                $table->dropForeign(['merchant_id']);
                $table->dropUnique(['merchant_id', 'gateway_id']);
                $table->dropColumn('merchant_id');
            });
        }

        if (! Schema::hasColumn('merchant_gateways', 'user_id')) {
            Schema::table('merchant_gateways', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->after('id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['user_id', 'gateway_id']);
            });
        }
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('merchant_gateways', 'user_id')) {
            Schema::table('merchant_gateways', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'gateway_id']);
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (! Schema::hasColumn('merchant_gateways', 'merchant_id')) {
            Schema::table('merchant_gateways', function (Blueprint $table) {
                $table->foreignUuid('merchant_id')->after('id')->constrained('merchants')->cascadeOnDelete();
                $table->unique(['merchant_id', 'gateway_id']);
            });
        }
    }
};
