<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('site_monitoring_enabled')->default(false)->after('status');
            $table->string('monitor_path', 200)->default('/')->after('site_monitoring_enabled');
            $table->unsignedSmallInteger('monitor_expected_status')->default(200)->after('monitor_path');
            $table->unsignedTinyInteger('monitor_consecutive_failures')->default(3)->after('monitor_expected_status');
            $table->unsignedSmallInteger('monitor_cooldown_minutes')->default(30)->after('monitor_consecutive_failures');
            $table->string('monitor_last_status')->nullable()->after('monitor_cooldown_minutes');
            $table->timestamp('monitor_last_checked_at')->nullable()->after('monitor_last_status');
            $table->unsignedInteger('monitor_last_latency_ms')->nullable()->after('monitor_last_checked_at');
            $table->string('monitor_last_error')->nullable()->after('monitor_last_latency_ms');
            $table->unsignedTinyInteger('monitor_consecutive_down')->default(0)->after('monitor_last_error');
            $table->string('dns_last_status')->nullable()->after('monitor_consecutive_down');
            $table->timestamp('dns_last_checked_at')->nullable()->after('dns_last_status');
            $table->string('dns_last_error')->nullable()->after('dns_last_checked_at');
        });

        Schema::create('site_monitor_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('open')->index();
            $table->string('message');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_monitor_incidents');

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'site_monitoring_enabled',
                'monitor_path',
                'monitor_expected_status',
                'monitor_consecutive_failures',
                'monitor_cooldown_minutes',
                'monitor_last_status',
                'monitor_last_checked_at',
                'monitor_last_latency_ms',
                'monitor_last_error',
                'monitor_consecutive_down',
                'dns_last_status',
                'dns_last_checked_at',
                'dns_last_error',
            ]);
        });
    }
};
