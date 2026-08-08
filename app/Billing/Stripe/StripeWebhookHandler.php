<?php

namespace App\Billing\Stripe;

use App\Actions\Servers\ConfirmManagedServerPayment;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingPaymentFailedNotification;
use Carbon\CarbonImmutable;

final class StripeWebhookHandler
{
    public function handle(array $event): void
    {
        $type = (string) data_get($event, 'type');
        $object = (array) data_get($event, 'data.object', []);
        if ($type === 'checkout.session.completed') {
            $this->checkoutCompleted($object);
        } elseif (str_starts_with($type, 'customer.subscription.')) {
            $this->subscriptionChanged($object);
        } elseif (str_starts_with($type, 'invoice.')) {
            $this->invoiceChanged($object, $type);
        }
    }

    private function checkoutCompleted(array $object): void
    {
        if (data_get($object, 'metadata.purpose') === 'managed_server') {
            app(ConfirmManagedServerPayment::class)->fromCheckoutSession($object, true);

            return;
        }

        $user = User::find(data_get($object, 'metadata.user_id') ?: data_get($object, 'client_reference_id'));
        $plan = Plan::find(data_get($object, 'metadata.plan_id'));
        if (! $user || ! $plan) {
            return;
        }
        $user->forceFill(['stripe_customer_id' => data_get($object, 'customer')])->save();
        if ($providerId = data_get($object, 'subscription')) {
            Subscription::firstOrCreate(['provider_subscription_id' => $providerId], ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'incomplete', 'provider' => 'stripe', 'provider_metadata' => ['checkout_session_id' => data_get($object, 'id')]]);
        }
    }

    private function subscriptionChanged(array $object): void
    {
        // Managed VPS subscriptions are tracked on server metadata — never as plan entitlements.
        if (data_get($object, 'metadata.purpose') === 'managed_server') {
            $this->managedServerSubscriptionChanged($object);

            return;
        }

        $user = $this->user($object);
        $priceId = data_get($object, 'items.data.0.price.id');
        $plan = Plan::find(data_get($object, 'metadata.plan_id')) ?: Plan::where('stripe_monthly_price_id', $priceId)->orWhere('stripe_yearly_price_id', $priceId)->first();
        if (! $user || ! $plan || ! data_get($object, 'id')) {
            return;
        }
        $status = (string) data_get($object, 'status', 'incomplete');
        $periodStart = data_get($object, 'current_period_start') ?: data_get($object, 'items.data.0.current_period_start');
        $periodEnd = data_get($object, 'current_period_end') ?: data_get($object, 'items.data.0.current_period_end');
        $subscription = Subscription::updateOrCreate(['provider_subscription_id' => data_get($object, 'id')], ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => $status, 'provider' => 'stripe', 'provider_price_id' => $priceId, 'trial_ends_at' => $this->date(data_get($object, 'trial_end')), 'current_period_starts_at' => $this->date($periodStart), 'current_period_ends_at' => $this->date($periodEnd), 'cancel_at_period_end' => (bool) data_get($object, 'cancel_at_period_end', false), 'canceled_at' => $this->date(data_get($object, 'canceled_at')), 'ended_at' => in_array($status, ['canceled', 'unpaid'], true) ? now() : null, 'provider_metadata' => ['livemode' => (bool) data_get($object, 'livemode', false)]]);
        if (in_array($status, ['active', 'trialing'], true)) {
            $user->subscriptions()->whereKeyNot($subscription->id)->whereIn('status', ['active', 'trialing'])->update(['status' => 'ended', 'ended_at' => now()]);
        }
    }

    private function managedServerSubscriptionChanged(array $object): void
    {
        $server = Server::find(data_get($object, 'metadata.server_id'))
            ?: Server::query()->where('metadata->stripe_subscription_id', data_get($object, 'id'))->first();
        if (! $server || ! $server->isManaged()) {
            return;
        }

        $status = (string) data_get($object, 'status', 'incomplete');
        $metadata = array_merge($server->metadata ?? [], [
            'stripe_subscription_id' => data_get($object, 'id'),
            'stripe_subscription_status' => $status,
        ]);

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $metadata['payment_status'] = $status;
        } elseif (in_array($status, ['active', 'trialing'], true)) {
            $metadata['payment_status'] = 'paid';
        }

        $server->forceFill(['metadata' => $metadata])->save();
    }

    private function invoiceChanged(array $object, string $eventType): void
    {
        $user = $this->user($object);
        if (! $user || ! data_get($object, 'id')) {
            return;
        }
        $providerSubscriptionId = data_get($object, 'subscription') ?: data_get($object, 'parent.subscription_details.subscription');
        $subscription = $providerSubscriptionId ? Subscription::where('provider_subscription_id', $providerSubscriptionId)->first() : null;
        $invoice = BillingInvoice::updateOrCreate(['provider_invoice_id' => data_get($object, 'id')], ['user_id' => $user->id, 'subscription_id' => $subscription?->id, 'provider' => 'stripe', 'number' => data_get($object, 'number'), 'status' => data_get($object, 'status', str_ends_with($eventType, 'payment_failed') ? 'open' : 'draft'), 'currency' => strtoupper(data_get($object, 'currency', 'usd')), 'subtotal' => (int) data_get($object, 'subtotal', 0), 'tax' => (int) data_get($object, 'total_tax_amounts.0.amount', 0), 'total' => (int) data_get($object, 'total', 0), 'hosted_invoice_url' => data_get($object, 'hosted_invoice_url'), 'invoice_pdf' => data_get($object, 'invoice_pdf'), 'period_starts_at' => $this->date(data_get($object, 'period_start')), 'period_ends_at' => $this->date(data_get($object, 'period_end')), 'paid_at' => data_get($object, 'status') === 'paid' ? $this->date(data_get($object, 'status_transitions.paid_at')) ?? now() : null, 'provider_metadata' => ['event_type' => $eventType]]);
        if ($eventType === 'invoice.payment_failed') {
            $user->notify(new BillingPaymentFailedNotification($invoice->id));
        }
    }

    private function user(array $object): ?User
    {
        return User::find(data_get($object, 'metadata.user_id')) ?: User::where('stripe_customer_id', data_get($object, 'customer'))->first();
    }

    private function date(mixed $timestamp): ?CarbonImmutable
    {
        return $timestamp ? CarbonImmutable::createFromTimestampUTC((int) $timestamp) : null;
    }
}
