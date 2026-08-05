<?php

namespace Tests\Feature;

use App\Billing\Stripe\StripeWebhookHandler;
use App\Jobs\Billing\ProcessStripeWebhookJob;
use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\BillingPaymentFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosted_checkout_uses_mapped_price_and_customer_metadata(): void
    {
        config(['services.stripe.secret' => 'sk_test_Uplary', 'services.stripe.automatic_tax' => true]);
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plan = $this->plan();

        $this->actingAs($user)->post('/billing/checkout', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        Http::assertSent(fn ($request) => $request['mode'] === 'subscription' && $request['line_items'][0]['price'] === 'price_monthly' && $request['metadata']['user_id'] === (string) $user->id && $request['automatic_tax']['enabled'] === 'true');
    }

    public function test_webhook_rejects_invalid_or_expired_signatures_and_persists_once(): void
    {
        Queue::fake();
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $payload = json_encode(['id' => 'evt_once', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1']]], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, 't='.time().',v1=invalid')->assertBadRequest();
        $old = time() - 1000;
        $this->postWebhook($payload, 't='.$old.',v1='.hash_hmac('sha256', $old.'.'.$payload, 'whsec_test'))->assertBadRequest();
        $signature = $this->signature($payload);
        $this->postWebhook($payload, $signature)->assertOk();
        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame(1, BillingWebhookEvent::where('provider_event_id', 'evt_once')->count());
        Queue::assertPushed(ProcessStripeWebhookJob::class, 1);
        $raw = DB::table('billing_webhook_events')->where('provider_event_id', 'evt_once')->value('payload');
        $this->assertStringNotContainsString('checkout.session.completed', $raw);
    }

    public function test_checkout_and_subscription_events_activate_entitlements_idempotently(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $free = $this->plan('free', 'price_free');
        $pro = $this->plan('pro', 'price_monthly');
        $user->subscriptions()->create(['plan_id' => $free->id, 'provider' => 'system', 'status' => 'active']);
        $checkout = $this->event('evt_checkout', 'checkout.session.completed', ['id' => 'cs_1', 'client_reference_id' => (string) $user->id, 'customer' => 'cus_123', 'subscription' => 'sub_123', 'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $pro->id]]);
        (new ProcessStripeWebhookJob($checkout->id))->handle(app(StripeWebhookHandler::class));
        $subscriptionEvent = $this->event('evt_subscription', 'customer.subscription.updated', ['id' => 'sub_123', 'customer' => 'cus_123', 'status' => 'active', 'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $pro->id], 'items' => ['data' => [['price' => ['id' => 'price_monthly'], 'current_period_start' => time(), 'current_period_end' => time() + 2592000]]]]);
        (new ProcessStripeWebhookJob($subscriptionEvent->id))->handle(app(StripeWebhookHandler::class));
        (new ProcessStripeWebhookJob($subscriptionEvent->id))->handle(app(StripeWebhookHandler::class));
        $lateCheckout = $this->event('evt_checkout_late', 'checkout.session.completed', ['id' => 'cs_late', 'client_reference_id' => (string) $user->id, 'customer' => 'cus_123', 'subscription' => 'sub_123', 'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $pro->id]]);
        (new ProcessStripeWebhookJob($lateCheckout->id))->handle(app(StripeWebhookHandler::class));

        $this->assertSame('cus_123', $user->fresh()->stripe_customer_id);
        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $this->assertSame($pro->id, $active->plan_id);
        $this->assertSame('sub_123', $active->provider_subscription_id);
        $this->assertSame(1, $user->subscriptions()->where('provider_subscription_id', 'sub_123')->count());
        $this->assertSame('processed', $subscriptionEvent->fresh()->status);
        $this->assertSame('active', $active->fresh()->status);
    }

    public function test_invoice_webhook_creates_customer_invoice_history(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'stripe_customer_id' => 'cus_invoice']);
        $event = $this->event('evt_invoice', 'invoice.paid', ['id' => 'in_123', 'customer' => 'cus_invoice', 'number' => 'INV-0001', 'status' => 'paid', 'currency' => 'usd', 'subtotal' => 2900, 'total_tax_amounts' => [['amount' => 145]], 'total' => 3045, 'hosted_invoice_url' => 'https://invoice.stripe.com/i/123', 'period_start' => time(), 'period_end' => time() + 2592000, 'status_transitions' => ['paid_at' => time()]]);

        (new ProcessStripeWebhookJob($event->id))->handle(app(StripeWebhookHandler::class));

        $invoice = $user->billingInvoices()->firstOrFail();
        $this->assertSame('INV-0001', $invoice->number);
        $this->assertSame(3045, $invoice->total);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_customer_portal_requires_and_uses_provider_customer(): void
    {
        config(['services.stripe.secret' => 'sk_test_Uplary']);
        Http::fake(['https://api.stripe.com/v1/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.com/p/session/test'])]);
        $user = User::factory()->create(['email_verified_at' => now(), 'stripe_customer_id' => 'cus_portal']);

        $this->actingAs($user)->post('/billing/portal')->assertRedirect('https://billing.stripe.com/p/session/test');
        Http::assertSent(fn ($request) => $request['customer'] === 'cus_portal');
    }

    public function test_failed_invoice_notifies_customer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now(), 'stripe_customer_id' => 'cus_failed']);
        $event = $this->event('evt_failed', 'invoice.payment_failed', ['id' => 'in_failed', 'customer' => 'cus_failed', 'number' => 'INV-FAIL', 'status' => 'open', 'currency' => 'usd', 'total' => 2900]);

        (new ProcessStripeWebhookJob($event->id))->handle(app(StripeWebhookHandler::class));

        Notification::assertSentTo($user, BillingPaymentFailedNotification::class);
    }

    private function postWebhook(string $payload, string $signature)
    {
        return $this->call('POST', '/api/billing/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    private function signature(string $payload): string
    {
        $timestamp = time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');
    }

    private function event(string $id, string $type, array $object): BillingWebhookEvent
    {
        return BillingWebhookEvent::create(['provider_event_id' => $id, 'provider' => 'stripe', 'type' => $type, 'payload' => ['id' => $id, 'type' => $type, 'data' => ['object' => $object]]]);
    }

    private function plan(string $slug = 'pro', string $price = 'price_monthly'): Plan
    {
        return Plan::create(['name' => ucfirst($slug), 'slug' => $slug, 'monthly_price' => 2900, 'yearly_price' => 29000, 'stripe_monthly_price_id' => $price, 'stripe_yearly_price_id' => $price.'_yearly', 'limits' => ['servers' => 10], 'active' => true, 'public' => true]);
    }
}
