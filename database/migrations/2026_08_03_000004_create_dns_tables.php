<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DNS accounts hold the provider credential; zones record which domains this platform has
 * been pointed at. Records themselves are deliberately not stored: a zone can be edited in
 * Cloudflare's dashboard or by another tool at any moment, and a local mirror would show
 * whatever it last saw with complete confidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // foreignId, not foreignUuid: users.id is a bigint.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 32)->default('cloudflare')->index();
            $table->text('credentials');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dns_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dns_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('provider_zone_id');
            $table->string('status', 32)->default('active');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            // The same zone imported twice would give two pages editing one set of records.
            $table->unique(['dns_account_id', 'provider_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_zones');
        Schema::dropIfExists('dns_accounts');
    }
};
