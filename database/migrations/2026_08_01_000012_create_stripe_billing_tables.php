<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable()->after('yearly_price');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('stripe_customer_id')->nullable()->unique()->after('current_team_id'));
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('provider_price_id')->nullable()->after('provider_subscription_id');
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('provider_metadata')->nullable();
        });
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->default('stripe');
            $table->string('provider_event_id')->unique();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('provider_invoice_id')->unique();
            $table->string('number')->nullable();
            $table->string('status')->index();
            $table->string('currency', 3);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('hosted_invoice_url', 2048)->nullable();
            $table->string('invoice_pdf', 2048)->nullable();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_webhook_events');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['provider_price_id', 'current_period_starts_at', 'canceled_at', 'provider_metadata']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['stripe_monthly_price_id', 'stripe_yearly_price_id']));
    }
};
