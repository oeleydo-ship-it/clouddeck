<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The third and fourth instances of one mistake: a soft-deleted row keeps its place in a
 * unique index, so the name it used is reserved forever. Deleting a database called "wp"
 * and creating another meant an integrity violation surfacing as a 500 — the same failure
 * already fixed for queue workers and site domains.
 *
 * Including deleted_at frees the name once the row is trashed. Live rows stay guarded by
 * the index for the composite keys, and by request validation everywhere, since MySQL
 * treats NULL deleted_at values as distinct and will not catch a duplicate among them.
 *
 * Each replacement index is created before its predecessor is dropped, never the reverse:
 * where the old index is the only one covering a foreign key column, dropping it first
 * fails on MySQL with errno 1553.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const INDEXES = [
        'managed_databases' => ['server_id', 'engine', 'name'],
        'plans' => ['slug'],
        'posts' => ['slug'],
        'teams' => ['slug'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique([...$columns, 'deleted_at']));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($columns));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique([...$columns, 'deleted_at']));
        }
    }
};
