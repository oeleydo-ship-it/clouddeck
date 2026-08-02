<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queue workers are soft deleted, so a unique index on (site_id, name) kept a removed
 * worker's name reserved forever: recreating a Reverb worker called "reverb" failed with
 * an integrity constraint violation instead of succeeding. Including deleted_at frees the
 * name once the row is trashed; live rows are still guarded by request validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'name']);
            $table->unique(['site_id', 'name', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'name', 'deleted_at']);
            $table->unique(['site_id', 'name']);
        });
    }
};
