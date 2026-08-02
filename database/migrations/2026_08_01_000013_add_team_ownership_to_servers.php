<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->foreignUuid('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'status']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
