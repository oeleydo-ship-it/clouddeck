<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->string('runtime_status')->nullable()->after('status');
            $table->text('runtime_output')->nullable()->after('runtime_status');
            $table->timestamp('runtime_checked_at')->nullable()->after('runtime_output');
        });
    }

    public function down(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropColumn(['runtime_status', 'runtime_output', 'runtime_checked_at']);
        });
    }
};
