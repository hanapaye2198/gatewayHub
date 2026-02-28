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
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->boolean('is_settled')->default(false)->after('metadata');
            $table->timestamp('settled_at')->nullable()->after('is_settled');
            $table->index(['entry_type', 'is_settled'], 'wallet_txn_entry_settled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_txn_entry_settled_idx');
            $table->dropColumn(['is_settled', 'settled_at']);
        });
    }
};
