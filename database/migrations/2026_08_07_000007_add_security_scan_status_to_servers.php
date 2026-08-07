<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('security_scan_status', 20)->default('idle')->after('security_scanned_at')->index();
            $table->string('security_scan_message', 500)->nullable()->after('security_scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['security_scan_status', 'security_scan_message']);
        });
    }
};
