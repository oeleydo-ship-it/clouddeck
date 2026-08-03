<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same guard as the migration beside it: both shipped in one commit, so whatever
        // interrupted that run could have left this column applied but unrecorded too.
        if (Schema::hasColumn('notification_channels', 'events')) {
            return;
        }

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
