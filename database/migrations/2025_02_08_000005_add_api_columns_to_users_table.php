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
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_key')->unique()->nullable()->after('remember_token');
            $table->string('api_secret')->nullable()->after('api_key');
            $table->boolean('is_active')->default(true)->after('api_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key']);
            $table->dropColumn(['api_key', 'api_secret', 'is_active']);
        });
    }
};
