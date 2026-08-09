<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_backups', function (Blueprint $table) {
            $table->string('kind')->default('wordpress_local')->after('label');
            $table->string('source')->default('manual')->after('kind');
            $table->string('disk')->nullable()->after('source');
            $table->string('disk_path')->nullable()->after('disk');
            $table->string('checksum')->nullable()->after('disk_path');
        });
    }

    public function down(): void
    {
        Schema::table('site_backups', function (Blueprint $table) {
            $table->dropColumn(['kind', 'source', 'disk', 'disk_path', 'checksum']);
        });
    }
};
