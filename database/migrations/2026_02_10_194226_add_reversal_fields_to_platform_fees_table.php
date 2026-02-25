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
        Schema::table('platform_fees', function (Blueprint $table) {
            $table->text('reversal_reason')->nullable()->after('status');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_fees', function (Blueprint $table) {
            $table->dropColumn(['reversal_reason', 'reversed_at']);
        });
    }
};
