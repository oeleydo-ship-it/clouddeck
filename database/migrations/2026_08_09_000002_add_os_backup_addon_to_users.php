<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('os_backup_addon_gb')->default(0)->after('stripe_customer_id');
            $table->string('os_backup_stripe_subscription_id')->nullable()->after('os_backup_addon_gb');
            $table->string('os_backup_stripe_subscription_status')->nullable()->after('os_backup_stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'os_backup_addon_gb',
                'os_backup_stripe_subscription_id',
                'os_backup_stripe_subscription_status',
            ]);
        });
    }
};
