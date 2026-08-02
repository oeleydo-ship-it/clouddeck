<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('managed_database_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('frequency');
            $table->string('run_at', 5)->default('02:00');
            $table->string('timezone')->default('UTC');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedSmallInteger('retention_count')->default(7);
            $table->string('disk')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'type', 'enabled']);
        });

        Schema::table('database_backups', function (Blueprint $table) {
            $table->foreignUuid('backup_policy_id')->nullable()->after('managed_database_id')->constrained()->nullOnDelete();
            $table->string('source')->default('manual')->after('type');
            $table->string('checksum', 64)->nullable()->after('size');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
        });

        Schema::create('backup_restores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('database_backup_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('managed_database_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('server_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('backup_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('provider_snapshot_id')->nullable()->index();
            $table->string('provider_action_id')->nullable();
            $table->decimal('size_gigabytes', 10, 2)->nullable();
            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_snapshots');
        Schema::dropIfExists('backup_restores');
        Schema::table('database_backups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backup_policy_id');
            $table->dropColumn(['source', 'checksum', 'completed_at', 'expires_at']);
        });
        Schema::dropIfExists('backup_policies');
    }
};
