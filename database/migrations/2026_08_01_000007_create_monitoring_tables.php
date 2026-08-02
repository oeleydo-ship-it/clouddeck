<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->text('monitoring_secret')->nullable();
            $table->boolean('monitoring_enabled')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
        });

        Schema::table('server_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->unsignedBigInteger('memory_total_bytes')->nullable();
            $table->unsignedBigInteger('disk_used_bytes')->nullable();
            $table->unsignedBigInteger('disk_total_bytes')->nullable();
            $table->unsignedBigInteger('network_rx_bytes')->nullable();
            $table->unsignedBigInteger('network_tx_bytes')->nullable();
            $table->json('processes')->nullable();
            $table->index(['server_id', 'recorded_at']);
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric', 40);
            $table->string('operator', 4)->default('gte');
            $table->decimal('threshold', 12, 2);
            $table->unsignedTinyInteger('consecutive_samples')->default(3);
            $table->unsignedSmallInteger('cooldown_minutes')->default(30);
            $table->string('severity', 20)->default('warning');
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('alert_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->string('severity', 20);
            $table->string('metric', 40);
            $table->decimal('value', 12, 2);
            $table->decimal('threshold', 12, 2);
            $table->text('message');
            $table->timestamp('started_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['alert_rule_id', 'status']);
        });

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->text('configuration')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('alert_incidents');
        Schema::dropIfExists('alert_rules');
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'recorded_at']);
            $table->dropColumn(['memory_used_bytes', 'memory_total_bytes', 'disk_used_bytes', 'disk_total_bytes', 'network_rx_bytes', 'network_tx_bytes', 'processes']);
        });
        Schema::table('servers', fn (Blueprint $table) => $table->dropColumn(['monitoring_secret', 'monitoring_enabled', 'last_seen_at']));
    }
};
