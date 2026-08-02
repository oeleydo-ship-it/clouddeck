<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A custom server is one the operator already runs: CloudDeck connects to it over SSH by
 * IP rather than creating it through a provider API. It therefore has no cloud account to
 * belong to, and may not answer on port 22.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('public_ip');
        });

        // Dropping the foreign key first: making the column nullable while it is still
        // constrained is rejected on MySQL.
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['cloud_account_id']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->uuid('cloud_account_id')->nullable()->change();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('cloud_account_id')->references('id')->on('cloud_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['cloud_account_id']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->uuid('cloud_account_id')->nullable(false)->change();
            $table->dropColumn('ssh_port');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('cloud_account_id')->references('id')->on('cloud_accounts')->restrictOnDelete();
        });
    }
};
