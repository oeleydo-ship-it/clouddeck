<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_backups', fn (Blueprint $table) => $table->string('disk')->default('local')->after('status'));
        Schema::table('file_operations', fn (Blueprint $table) => $table->string('disk')->default('local')->after('result'));
    }

    public function down(): void
    {
        Schema::table('database_backups', fn (Blueprint $table) => $table->dropColumn('disk'));
        Schema::table('file_operations', fn (Blueprint $table) => $table->dropColumn('disk'));
    }
};
