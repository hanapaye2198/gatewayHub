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
        Schema::create('coins_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->index();
            $table->string('reference_id')->nullable()->index();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 32)->default('PENDING');
            $table->text('qr_code_string')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coins_transactions');
    }
};
