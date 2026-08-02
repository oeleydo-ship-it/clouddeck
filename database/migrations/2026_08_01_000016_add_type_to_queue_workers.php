<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->string('type')->default('queue')->after('name');
            $table->unsignedInteger('port')->nullable()->after('memory');
        });
    }

    public function down(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropColumn(['type', 'port']);
        });
    }
};
