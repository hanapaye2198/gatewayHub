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
        Schema::create('platform_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20)->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('fee_type', 20)->index();
            $table->decimal('fee_value', 10, 4);
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->timestamps();
        });

        $feePercent = (float) (config('platform.fees.percentage', 1.5) / 100);
        DB::table('platform_fee_rules')->insert([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => $feePercent,
            'is_active' => true,
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fee_rules');
    }
};
