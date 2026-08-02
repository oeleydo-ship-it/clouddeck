<?php

namespace App\Billing\Stripe;

use App\Models\Plan;
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
        $price = $cycle === 'yearly' ? $plan->stripe_yearly_price_id : $plan->stripe_monthly_price_id;
        if (! $price) {
            throw new RuntimeException('This plan does not have a Stripe price for the selected cycle.');
        }
        $payload = ['mode' => 'subscription', 'line_items' => [['price' => $price, 'quantity' => 1]], 'client_reference_id' => (string) $user->id, 'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => route('billing.index'), 'allow_promotion_codes' => 'true', 'automatic_tax' => ['enabled' => config('services.stripe.automatic_tax') ? 'true' : 'false'], 'metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => $cycle], 'subscription_data' => ['metadata' => ['user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => $cycle]]];
        $payload[$user->stripe_customer_id ? 'customer' : 'customer_email'] = $user->stripe_customer_id ?: $user->email;

        return $this->client()->withHeader('Idempotency-Key', (string) Str::uuid())->post('/checkout/sessions', $payload)->throw()->json();
    }

    public function portal(User $user): array
    {
        if (! $user->stripe_customer_id) {
            throw new RuntimeException('No Stripe customer is associated with this account.');
        }

        return $this->client()->post('/billing_portal/sessions', ['customer' => $user->stripe_customer_id, 'return_url' => route('billing.index')])->throw()->json();
    }
}
