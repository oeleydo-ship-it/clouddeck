<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('security_scanned_at')->nullable()->index();
        });

        Schema::create('security_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('detector_key', 100);
            $table->string('rule_name');
            $table->string('source', 40);
            $table->string('severity', 20)->index();
            $table->string('status', 20)->default('open')->index();
            $table->string('source_ip', 45)->nullable()->index();
            $table->string('title');
            $table->text('summary');
            $table->json('evidence')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->string('mitigation_status', 30)->nullable();
            $table->string('mitigation_action', 50)->nullable();
            $table->foreignUuid('firewall_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['server_id', 'detector_key', 'status']);
            $table->index(['site_id', 'detector_key', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
        Schema::table('servers', fn (Blueprint $table) => $table->dropColumn('security_scanned_at'));
    }
};
