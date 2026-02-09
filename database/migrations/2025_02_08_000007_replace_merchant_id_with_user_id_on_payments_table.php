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
        if (Schema::hasColumn('payments', 'merchant_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['merchant_id']);
                $table->dropColumn('merchant_id');
            });
        }

        if (! Schema::hasColumn('payments', 'user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('user_id')->after('id')->constrained('users')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('merchant_id')->after('id')->constrained('merchants');
        });
    }
};
