<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            // Null means every event, so channels that existed before this keep behaving as
            // they did rather than going quiet.
            $table->json('events')->nullable()->after('configuration');
        });
    }

    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropColumn('events');
        });
    }
};
