<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('queue_failed_count')->nullable()->after('status');
            $table->timestamp('queue_checked_at')->nullable()->after('queue_failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['queue_failed_count', 'queue_checked_at']);
        });
    }
};
