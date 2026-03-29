<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'onboarding_gateways_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('onboarding_gateways_at')->nullable()->after('merchant_id');
            });
        }

        if (! Schema::hasColumn('users', 'onboarding_completed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_gateways_at');
            });
        }

        $now = now();
        DB::table('users')
            ->whereNotNull('merchant_id')
            ->whereNull('onboarding_completed_at')
            ->update([
                'onboarding_gateways_at' => $now,
                'onboarding_completed_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'onboarding_gateways_at')) {
                $table->dropColumn('onboarding_gateways_at');
            }
            if (Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->dropColumn('onboarding_completed_at');
            }
        });
    }
};
