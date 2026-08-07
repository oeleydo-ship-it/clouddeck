<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_detection_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('rule_overrides')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_detection_settings');
    }
};
