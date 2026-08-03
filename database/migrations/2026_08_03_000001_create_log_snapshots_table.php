<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->unsignedSmallInteger('lines')->default(200);
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            // Bounded on the way in: a log read is a snapshot for a person to look at, not an
            // archive, and an unbounded one would be limited only by how large the file is.
            $table->mediumText('output')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'source', 'created_at']);
            $table->index(['server_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_snapshots');
    }
};
