<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surepay_batch_settings', function (Blueprint $table) {
            $table->unsignedInteger('batch_interval_seconds')->default(900)->after('batch_interval_minutes');
        });

        DB::table('surepay_batch_settings')->update([
            'batch_interval_seconds' => DB::raw('batch_interval_minutes * 60'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surepay_batch_settings', function (Blueprint $table) {
            $table->dropColumn('batch_interval_seconds');
        });
    }
};
