<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('api_key_encrypted')->nullable()->after('api_key_hash');
        });

        DB::table('merchants')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($merchants): void {
                foreach ($merchants as $merchant) {
                    $apiKey = (string) $merchant->api_key;

                    DB::table('merchants')
                        ->where('id', $merchant->id)
                        ->update([
                            'api_key' => null,
                            'api_key_hash' => hash('sha256', $apiKey),
                            'api_key_last_four' => substr($apiKey, -4),
                            'api_key_encrypted' => Crypt::encryptString($apiKey),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('api_key_encrypted');
        });
    }
};
