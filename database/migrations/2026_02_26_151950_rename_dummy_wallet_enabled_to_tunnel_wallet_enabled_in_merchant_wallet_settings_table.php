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
        Schema::table('merchant_wallet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_wallet_settings', 'dummy_wallet_enabled')
                && ! Schema::hasColumn('merchant_wallet_settings', 'tunnel_wallet_enabled')) {
                $table->renameColumn('dummy_wallet_enabled', 'tunnel_wallet_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_wallet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_wallet_settings', 'tunnel_wallet_enabled')
                && ! Schema::hasColumn('merchant_wallet_settings', 'dummy_wallet_enabled')) {
                $table->renameColumn('tunnel_wallet_enabled', 'dummy_wallet_enabled');
            }
        });
    }
};
