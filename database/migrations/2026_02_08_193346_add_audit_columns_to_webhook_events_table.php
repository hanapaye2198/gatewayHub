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
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('event_id');
            $table->json('headers')->nullable()->after('payload');
            $table->string('status')->default('received')->after('processed_at');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropColumn(['payload', 'headers', 'status', 'error_message']);
        });
    }
};
