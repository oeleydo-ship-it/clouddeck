<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_impersonation_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('support_mode', 20)->default('full'); // full | read_only
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_identifier', 100)->nullable();
            $table->string('status', 20)->default('active'); // active | ended | terminated
            $table->timestamps();

            $table->index(['target_user_id', 'status']);
            $table->index(['admin_user_id', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_impersonation_sessions');
    }
};
