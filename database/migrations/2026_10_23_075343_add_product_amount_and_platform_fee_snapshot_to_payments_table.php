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
            if (! Schema::hasColumn('payments', 'product_amount')) {
                $table->decimal('product_amount', 15, 2)->nullable();
            }

            if (! Schema::hasColumn('payments', 'payer_fee_amount')) {
                $table->decimal('payer_fee_amount', 15, 2)->nullable();
            }

            if (! Schema::hasColumn('payments', 'platform_fee_rate')) {
                $table->decimal('platform_fee_rate', 8, 6)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnsToDrop = array_values(array_filter(
            ['product_amount', 'payer_fee_amount'],
            static fn (string $column): bool => Schema::hasColumn('payments', $column),
        ));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }
};
