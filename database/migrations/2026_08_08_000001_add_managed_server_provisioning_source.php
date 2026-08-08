<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('provisioning_source', 20)->default('byos')->after('cloud_account_id');
        });

        // Existing custom SSH attaches have no cloud account and region=custom.
        DB::table('servers')
            ->whereNull('cloud_account_id')
            ->where('region', 'custom')
            ->update(['provisioning_source' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('provisioning_source');
        });
    }
};
