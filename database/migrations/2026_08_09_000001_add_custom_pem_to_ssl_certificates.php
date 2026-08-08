<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ssl_certificates', function (Blueprint $table) {
            $table->longText('certificate_pem')->nullable()->after('failure_reason');
            $table->longText('private_key_pem')->nullable()->after('certificate_pem');
        });
    }

    public function down(): void
    {
        Schema::table('ssl_certificates', function (Blueprint $table) {
            $table->dropColumn(['certificate_pem', 'private_key_pem']);
        });
    }
};
