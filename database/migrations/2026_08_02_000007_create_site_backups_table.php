<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The directory on the server holding the dump and the content archive.
            $table->string('label');
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_backups');
    }
};
