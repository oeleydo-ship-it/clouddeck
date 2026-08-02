<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('engine', 20);
            $table->string('name', 64);
            $table->string('username', 64);
            $table->text('password');
            $table->string('status')->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['server_id', 'engine', 'name']);
        });
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->json('domains');
            $table->string('provider')->default('letsencrypt');
            $table->string('status')->default('pending')->index();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('force_https')->default(true);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('expression', 100);
            $table->text('command');
            $table->boolean('enabled')->default(true);
            $table->string('status')->default('pending');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('queue_workers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('connection')->default('redis');
            $table->string('queue')->default('default');
            $table->unsignedTinyInteger('processes')->default(1);
            $table->unsignedSmallInteger('tries')->default(3);
            $table->unsignedInteger('timeout')->default(90);
            $table->unsignedSmallInteger('memory')->default(128);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['site_id', 'name']);
        });
        Schema::create('server_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('target')->nullable();
            $table->string('status')->default('pending')->index();
            $table->longText('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['server_operations', 'queue_workers', 'cron_jobs', 'ssl_certificates', 'managed_databases'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
