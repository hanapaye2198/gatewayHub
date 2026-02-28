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
            $table->string('tunnel_client_id', 255)->nullable()->after('default_currency');
            $table->text('tunnel_client_secret')->nullable()->after('tunnel_client_id');
            $table->string('tunnel_webhook_id', 255)->nullable()->after('tunnel_client_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_wallet_settings', function (Blueprint $table) {
            $table->dropColumn(['tunnel_client_id', 'tunnel_client_secret', 'tunnel_webhook_id']);
        });
    }
};
