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
        Schema::create('surepay_batch_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('batch_interval_minutes')->default(15);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_absolute_value', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surepay_batch_settings');
    }
};
