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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('platform_fee_rate', 6, 4)->nullable()->after('platform_fee');
            $table->decimal('convenience_fee', 15, 2)->nullable()->after('net_amount');
            $table->decimal('customer_total', 15, 2)->nullable()->after('convenience_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_rate', 'convenience_fee', 'customer_total']);
        });
    }
};
