<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A site is a Laravel application or a WordPress install. The two differ in how they are
 * deployed, what their document root is, and what configuration file they need written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('platform')->default('laravel')->after('domain')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn('platform'));
    }
};
