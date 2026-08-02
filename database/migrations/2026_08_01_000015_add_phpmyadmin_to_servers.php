<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('phpmyadmin_enabled')->default(false)->after('monitoring_enabled');
            $table->unsignedInteger('phpmyadmin_port')->nullable()->after('phpmyadmin_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['phpmyadmin_enabled', 'phpmyadmin_port']);
        });
    }
};
