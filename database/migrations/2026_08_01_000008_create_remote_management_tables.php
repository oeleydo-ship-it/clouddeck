<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('version');
            $table->text('settings');
            $table->string('status', 20)->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'type', 'version']);
        });

        Schema::create('file_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20)->index();
            $table->string('path', 500)->default('.');
            $table->string('destination', 500)->nullable();
            $table->longText('payload')->nullable();
            $table->longText('result')->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('terminal_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->text('command');
            $table->longText('output')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_commands');
        Schema::dropIfExists('file_operations');
        Schema::dropIfExists('site_configurations');
    }
};
