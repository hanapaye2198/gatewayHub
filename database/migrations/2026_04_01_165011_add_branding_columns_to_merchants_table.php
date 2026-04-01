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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('theme_color', 7)->nullable()->after('logo_path');
            $table->string('qr_display_name')->nullable()->after('theme_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'theme_color', 'qr_display_name']);
        });
    }
};
