<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = DB::table('gateways')->where('code', 'qrph')->first();
        if ($existing === null) {
            DB::table('gateways')->insert([
                'code' => 'qrph',
                'name' => 'QRPH',
                'driver_class' => 'App\Services\Gateways\Drivers\QrphDriver',
                'is_global_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('gateways')
            ->where('id', $existing->id)
            ->update([
                'name' => 'QRPH',
                'driver_class' => 'App\Services\Gateways\Drivers\QrphDriver',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op to avoid removing legacy gateway records.
    }
};
