<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deploying WordPress puts the files in place; the install itself happens afterwards in the
 * browser, and writes its tables to the database. Whether a site is installed is therefore
 * a fact about the database, not about whether a deployment succeeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('wordpress_installed_at')->nullable()->after('platform');
            $table->timestamp('wordpress_checked_at')->nullable()->after('wordpress_installed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn(['wordpress_installed_at', 'wordpress_checked_at']));
    }
};
