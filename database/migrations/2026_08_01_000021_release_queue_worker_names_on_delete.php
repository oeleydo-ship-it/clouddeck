<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queue workers are soft deleted, so a unique index on (site_id, name) kept a removed
 * worker's name reserved forever: recreating a Reverb worker called "reverb" failed with
 * an integrity constraint violation instead of succeeding. Including deleted_at frees the
 * name once the row is trashed; live rows are still guarded by request validation.
 *
 * The replacement index is created before the old one is dropped, and never the other way
 * round. MySQL requires an index covering a foreign key column, and (site_id, name) is the
 * only one covering site_id: dropping it first fails with errno 1553. Adding the wider
 * index first leaves site_id covered by its leftmost prefix, so the drop then succeeds.
 * Each statement gets its own Schema::table call to guarantee that ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->unique(['site_id', 'name', 'deleted_at']);
        });

        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('queue_workers', function (Blueprint $table) {
            $table->unique(['site_id', 'name']);
        });

        Schema::table('queue_workers', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'name', 'deleted_at']);
        });
    }
};
