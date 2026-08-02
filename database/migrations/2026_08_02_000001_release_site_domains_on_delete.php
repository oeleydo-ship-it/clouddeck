<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites are soft deleted, so a unique index on (server_id, domain) kept a removed site's
 * domain reserved forever: re-adding it failed with an integrity constraint violation that
 * reached the customer as a 500. Including deleted_at frees the domain once the row is
 * trashed, while live rows stay guarded — by the index and by request validation.
 *
 * The replacement index is created before the old one is dropped, never the other way
 * round. MySQL requires an index covering a foreign key column, and (server_id, domain) is
 * the only one covering server_id: dropping it first fails with errno 1553.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unique(['server_id', 'domain', 'deleted_at']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unique(['server_id', 'domain']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['server_id', 'domain', 'deleted_at']);
        });
    }
};
