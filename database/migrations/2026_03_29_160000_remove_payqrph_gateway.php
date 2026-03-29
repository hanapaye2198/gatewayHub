<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidate the legacy payqrph gateway into qrph and drop the duplicate row.
     */
    public function up(): void
    {
        $payqrph = DB::table('gateways')->where('code', 'payqrph')->first();
        if ($payqrph === null) {
            return;
        }

        $qrph = DB::table('gateways')->where('code', 'qrph')->first();

        DB::transaction(function () use ($payqrph, $qrph): void {
            DB::table('payments')->where('gateway_code', 'payqrph')->update(['gateway_code' => 'qrph']);

            if (Schema::hasTable('platform_fees')) {
                DB::table('platform_fees')->where('gateway_code', 'payqrph')->update(['gateway_code' => 'qrph']);
            }

            $payqrphId = $payqrph->id;

            if ($qrph !== null) {
                $qrphId = $qrph->id;
                $merchantGateways = DB::table('merchant_gateways')->where('gateway_id', $payqrphId)->get();
                foreach ($merchantGateways as $mg) {
                    $duplicate = DB::table('merchant_gateways')
                        ->where('merchant_id', $mg->merchant_id)
                        ->where('gateway_id', $qrphId)
                        ->exists();
                    if ($duplicate) {
                        DB::table('merchant_gateways')->where('id', $mg->id)->delete();
                    } else {
                        DB::table('merchant_gateways')->where('id', $mg->id)->update(['gateway_id' => $qrphId]);
                    }
                }
                DB::table('gateways')->where('id', $payqrphId)->delete();
            } else {
                DB::table('gateways')->where('id', $payqrphId)->update([
                    'code' => 'qrph',
                    'name' => 'QRPH',
                ]);
            }
        });
    }
};
