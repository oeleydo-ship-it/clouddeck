<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('auto_heal_enabled')->default(false)->after('monitoring_enabled');
            $table->unsignedSmallInteger('auto_heal_cooldown_minutes')->default(15)->after('auto_heal_enabled');
            $table->unsignedTinyInteger('auto_heal_consecutive_samples')->default(2)->after('auto_heal_cooldown_minutes');
            $table->json('auto_heal_last_actions')->nullable()->after('auto_heal_consecutive_samples');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'auto_heal_enabled',
                'auto_heal_cooldown_minutes',
                'auto_heal_consecutive_samples',
                'auto_heal_last_actions',
            ]);
        });
    }
};
