<?php

namespace App\Billing\Stripe;

use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class StripeClient
{
    private function client(): PendingRequest
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe billing is not configured.');
        }

        return Http::baseUrl('https://api.stripe.com/v1')->withToken($secret)->asForm()->acceptJson()->timeout(30)->retry(2, 500);
    }

    public function checkout(User $user, Plan $plan, string $cycle): array
    {
        $priceId = $cycle === 'yearly' ? $plan->stripe_yearly_price_id : $plan->stripe_monthly_price_id;
        if (! $priceId) {
            throw new RuntimeException('This plan does not have a Stripe price for the selected cycle.');
        }

        $expectedInterval = $cycle === 'yearly' ? 'year' : 'month';
        $price = $this->price((string) $priceId);
        $interval = (string) data_get($price, 'recurring.interval', '');
        if ($interval !== $expectedInterval) {
            throw new RuntimeException(
                $cycle === 'yearly'
                    ? 'The mapped Stripe yearly Price ID is not a yearly price. Update it under Admin → Payments.'
                    : 'The mapped Stripe monthly Price ID is not a monthly price. Update it under Admin → Payments.'
            );
        }

        $payload = [
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'client_reference_id' => (string) $user->id,
            'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.index'),
            'allow_promotion_codes' => 'true',
            'automatic_tax' => ['enabled' => config('services.stripe.automatic_tax') ? 'true' : 'false'],
            'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => $cycle],
            'subscription_data' => ['metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => $cycle]],
        ];
        $payload[$user->stripe_customer_id ? 'customer' : 'customer_email'] = $user->stripe_customer_id ?: $user->email;

        return $this->client()->withHeader('Idempotency-Key', (string) Str::uuid())->post('/checkout/sessions', $payload)->throw()->json();
    }

    public function price(string $priceId): array
    {
        return $this->client()->get('/prices/'.$priceId)->throw()->json();
    }

    /**
     * Recurring Checkout for a platform-managed VPS. Provisioning starts only after the webhook confirms payment.
     */
    public function checkoutManagedServer(User $user, Server $server, int $amountCents, string $productName): array
    {
        if ($amountCents < 50) {
            throw new RuntimeException('Managed server price must be at least $0.50/mo to collect payment.');
        }

        $meta = [
            'user_id' => (string) $user->id,
            'server_id' => (string) $server->id,
            'purpose' => 'managed_server',
        ];

        $priceData = [
            'currency' => 'usd',
            'unit_amount' => $amountCents,
            'recurring' => ['interval' => 'month'],
            'product_data' => [
                'name' => $productName,
                'description' => 'Managed server · '.$server->hostname,
            ],
        ];

        if (config('services.stripe.automatic_tax')) {
            $priceData['tax_behavior'] = 'exclusive';
        }

        $payload = [
            'mode' => 'subscription',
            'line_items' => [['price_data' => $priceData, 'quantity' => 1]],
            'client_reference_id' => (string) $user->id,
            'success_url' => route('servers.managed.checkout-success', $server).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('servers.manage', $server),
            'allow_promotion_codes' => 'true',
            'automatic_tax' => ['enabled' => config('services.stripe.automatic_tax') ? 'true' : 'false'],
            'metadata' => $meta,
            'subscription_data' => ['metadata' => $meta],
        ];
        $payload[$user->stripe_customer_id ? 'customer' : 'customer_email'] = $user->stripe_customer_id ?: $user->email;

        return $this->client()->withHeader('Idempotency-Key', (string) Str::uuid())->post('/checkout/sessions', $payload)->throw()->json();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->client()->delete('/subscriptions/'.$subscriptionId)->throw()->json();
    }

    public function checkoutSession(string $sessionId): array
    {
        return $this->client()->get('/checkout/sessions/'.$sessionId)->throw()->json();
    }

    public function portal(User $user): array
    {
        if (! $user->stripe_customer_id) {
            throw new RuntimeException('No Stripe customer is associated with this account.');
        }

        return $this->client()->post('/billing_portal/sessions', ['customer' => $user->stripe_customer_id, 'return_url' => route('billing.index')])->throw()->json();
    }
}
