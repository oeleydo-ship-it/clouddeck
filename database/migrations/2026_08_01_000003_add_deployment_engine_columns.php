<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('auto_deploy');
            $table->timestamp('last_deployed_at')->nullable()->after('status');
        });
        Schema::table('deployments', function (Blueprint $table) {
            $table->string('trigger')->default('manual')->after('status');
            $table->string('release')->nullable()->index()->after('trigger');
            $table->string('previous_release')->nullable()->after('release');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', fn (Blueprint $table) => $table->dropColumn(['trigger', 'release', 'previous_release']));
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn(['webhook_secret', 'last_deployed_at']));
    }
};
