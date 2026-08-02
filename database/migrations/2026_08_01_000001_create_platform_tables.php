<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->index();
            $table->string('timezone')->default('UTC');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        Schema::create('cloud_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('name');
            $table->text('credentials');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('fingerprint')->nullable()->index();
            $table->longText('public_key');
            $table->longText('private_key')->nullable();
            $table->timestamp('private_key_downloaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cloud_account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('ssh_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_id')->nullable()->index();
            $table->string('name');
            $table->string('hostname');
            $table->string('region');
            $table->string('size');
            $table->string('image')->default('ubuntu-24-04-x64');
            $table->string('status')->default('pending')->index();
            $table->ipAddress('public_ip')->nullable();
            $table->ipAddress('private_ip')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('current_step')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->index();
            $table->string('php_version')->default('8.4');
            $table->string('repository_url')->nullable();
            $table->string('branch')->default('main');
            $table->string('project_type')->default('laravel');
            $table->longText('deployment_script')->nullable();
            $table->boolean('auto_deploy')->default(false);
            $table->boolean('zero_downtime')->default(true);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['server_id', 'domain']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('commit_hash')->nullable();
            $table->string('commit_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
        });
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->longText('output');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('environment_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->longText('value');
            $table->boolean('is_secret')->default(true);
            $table->timestamps();
            $table->unique(['site_id', 'key']);
        });
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->decimal('memory_percent', 5, 2)->nullable();
            $table->decimal('disk_percent', 5, 2)->nullable();
            $table->decimal('load_average', 8, 2)->nullable();
            $table->json('services')->nullable();
            $table->timestamp('recorded_at')->index();
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event')->index();
            $table->text('description');
            $table->ipAddress('ip_address')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'server_metrics', 'environment_variables', 'deployment_logs', 'deployments', 'sites', 'servers', 'ssh_keys', 'cloud_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'timezone', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
