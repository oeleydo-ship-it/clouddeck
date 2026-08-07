<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('protocol', 8)->default('tcp');
            $table->string('port', 40)->nullable();
            $table->string('from_ip', 50)->nullable();
            $table->string('description')->nullable();
            $table->string('status')->default('pending');
            $table->text('status_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['server_id', 'status']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->string('firewall_status')->nullable()->after('phpmyadmin_port');
            $table->text('firewall_message')->nullable()->after('firewall_status');
            $table->text('firewall_remote_status')->nullable()->after('firewall_message');
            $table->timestamp('firewall_synced_at')->nullable()->after('firewall_remote_status');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'firewall_status',
                'firewall_message',
                'firewall_remote_status',
                'firewall_synced_at',
            ]);
        });

        Schema::dropIfExists('firewall_rules');
    }
};
