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
        Schema::table('surepay_batch_settings', function (Blueprint $table) {
            $table->timestamp('last_batch_settled_at')->nullable()->after('tax_absolute_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surepay_batch_settings', function (Blueprint $table) {
            $table->dropColumn('last_batch_settled_at');
        });
    }
};
