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
        $existingQrphGateway = DB::table('gateways')->where('code', 'qrph')->first();
        if ($existingQrphGateway !== null) {
            DB::table('gateways')
                ->where('id', $existingQrphGateway->id)
                ->update([
                    'code' => 'payqrph',
                    'name' => 'PayQRPH',
                    'driver_class' => 'App\Services\Gateways\Drivers\QrphDriver',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('gateways')->updateOrInsert(
                ['code' => 'payqrph'],
                [
                    'name' => 'PayQRPH',
                    'driver_class' => 'App\Services\Gateways\Drivers\QrphDriver',
                    'is_global_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('payments')->where('gateway_code', 'qrph')->update(['gateway_code' => 'payqrph']);
        DB::table('platform_fees')->where('gateway_code', 'qrph')->update(['gateway_code' => 'payqrph']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingPayQrphGateway = DB::table('gateways')->where('code', 'payqrph')->first();
        if ($existingPayQrphGateway === null) {
            return;
        }

        DB::table('gateways')
            ->where('id', $existingPayQrphGateway->id)
            ->update([
                'code' => 'qrph',
                'name' => 'QRPH',
                'driver_class' => 'App\Services\Gateways\Drivers\QrphDriver',
                'updated_at' => now(),
            ]);

        DB::table('payments')->where('gateway_code', 'payqrph')->update(['gateway_code' => 'qrph']);
        DB::table('platform_fees')->where('gateway_code', 'payqrph')->update(['gateway_code' => 'qrph']);
    }
};
