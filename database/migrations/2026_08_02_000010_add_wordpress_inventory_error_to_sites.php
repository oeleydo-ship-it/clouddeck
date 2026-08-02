<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // A read that fails silently is indistinguishable from one still running, which
            // left the page saying "reading from the server" for good.
            $table->text('wordpress_inventory_error')->nullable()->after('wordpress_inventory_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('wordpress_inventory_error');
        });
    }
};
