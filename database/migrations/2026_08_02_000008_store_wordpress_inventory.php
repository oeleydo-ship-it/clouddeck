<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a site has installed only exists on the server. Recording WP-CLI's answer lets the
 * page render plugins and themes as a list with actions, rather than asking the operator
 * to read raw command output.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('wordpress_inventory')->nullable()->after('wordpress_checked_at');
            $table->timestamp('wordpress_inventory_at')->nullable()->after('wordpress_inventory');
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn(['wordpress_inventory', 'wordpress_inventory_at']));
    }
};
