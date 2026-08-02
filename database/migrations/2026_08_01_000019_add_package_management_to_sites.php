<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('managed_packages')->nullable()->after('queue_checked_at');
            $table->json('installed_packages')->nullable()->after('managed_packages');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['managed_packages', 'installed_packages']);
        });
    }
};
