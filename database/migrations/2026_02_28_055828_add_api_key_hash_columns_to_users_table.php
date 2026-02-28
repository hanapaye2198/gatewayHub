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
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->unique()->after('api_key');
            $table->string('api_key_last_four', 4)->nullable()->after('api_key_hash');
        });

        DB::table('users')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $apiKey = (string) $user->api_key;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'api_key_hash' => hash('sha256', $apiKey),
                            'api_key_last_four' => substr($apiKey, -4),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn(['api_key_hash', 'api_key_last_four']);
        });
    }
};
