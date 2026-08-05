<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('environment')->default('production')->index()->after('status');
            $table->string('domain_source')->nullable()->after('environment');
            $table->string('staging_slug')->nullable()->after('domain_source');
            $table->foreignUuid('production_site_id')->nullable()->after('staging_slug')->constrained('sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_site_id');
            $table->dropColumn(['environment', 'domain_source', 'staging_slug']);
        });
    }
};
