<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded because this shipped once already: a deploy that created the table and
        // died before recording the run leaves the table in place but the migration
        // pending, and every later deploy then fails here. MySQL does not roll back DDL,
        // so the only way past it is to treat an existing table as work already done.
        if (Schema::hasTable('log_snapshots')) {
            return;
        }

        Schema::create('log_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            // foreignId, not foreignUuid: users.id is a bigint. Sites and servers are the
            // uuid-keyed tables, users are not.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
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
