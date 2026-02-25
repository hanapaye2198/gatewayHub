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
        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->timestamp('last_tested_at')->nullable()->after('config_json');
            $table->string('last_test_status', 32)->nullable()->after('last_tested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->dropColumn(['last_tested_at', 'last_test_status']);
        });
    }
};
