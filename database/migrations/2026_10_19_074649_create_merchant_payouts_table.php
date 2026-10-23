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
        Schema::create('merchant_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('purpose', 120)->nullable();
            $table->string('description', 255)->nullable();
            $table->text('destination_account');
            $table->string('status', 32)->default('submitting')->index();
            $table->string('reference_number', 64)->unique();
            $table->string('idempotency_key', 255);
            $table->char('request_fingerprint', 64);
            $table->string('paymongo_batch_transfer_id')->nullable()->index();
            $table->string('paymongo_transfer_id')->nullable()->unique();
            $table->string('provider_reference_number')->nullable();
            $table->decimal('provider_fee', 15, 2)->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('submission_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('funds_released_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key'], 'merchant_payout_idempotency_unique');
            $table->index(['merchant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_payouts');
    }
};
