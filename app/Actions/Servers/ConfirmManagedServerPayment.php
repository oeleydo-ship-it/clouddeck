<?php

namespace App\Actions\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;

/**
 * Marks a managed server paid and starts provisioning. Safe to call from webhooks and Checkout return.
 */
final class ConfirmManagedServerPayment
{
    public function __construct(private readonly ProvisionServer $provision) {}

    /**
     * @param  array<string, mixed>  $checkoutSession  Stripe Checkout Session object
     * @param  bool  $fromCompletedWebhook  When true, trust checkout.session.completed even if payment_status is omitted in the payload.
     */
    public function fromCheckoutSession(array $checkoutSession, bool $fromCompletedWebhook = false): ?Server
    {
        if (data_get($checkoutSession, 'metadata.purpose') !== 'managed_server') {
            return null;
        }

        $paymentStatus = (string) data_get($checkoutSession, 'payment_status', '');
        $sessionStatus = (string) data_get($checkoutSession, 'status', '');
        $paid = in_array($paymentStatus, ['paid', 'no_payment_required'], true)
            || $sessionStatus === 'complete'
            || ($fromCompletedWebhook && (data_get($checkoutSession, 'subscription') || $paymentStatus === ''));

        if (! $paid) {
            return null;
        }

        $user = User::find(data_get($checkoutSession, 'metadata.user_id') ?: data_get($checkoutSession, 'client_reference_id'));
        $server = Server::find(data_get($checkoutSession, 'metadata.server_id'));
        if (! $user || ! $server || ! $server->isManaged() || (string) $server->user_id !== (string) $user->id) {
            return null;
        }

        return $this->apply($server, $user, [
            'stripe_checkout_session_id' => data_get($checkoutSession, 'id'),
            'stripe_subscription_id' => data_get($checkoutSession, 'subscription'),
            'stripe_customer_id' => data_get($checkoutSession, 'customer'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stripeFields
     */
    public function apply(Server $server, User $user, array $stripeFields): Server
    {
        if ($customer = data_get($stripeFields, 'stripe_customer_id')) {
            $user->forceFill(['stripe_customer_id' => $customer])->save();
        }

        $metadata = array_merge($server->metadata ?? [], array_filter([
            'payment_status' => 'paid',
            'paid_at' => now()->toIso8601String(),
            'stripe_checkout_session_id' => data_get($stripeFields, 'stripe_checkout_session_id'),
            'stripe_subscription_id' => data_get($stripeFields, 'stripe_subscription_id'),
            'stripe_customer_id' => data_get($stripeFields, 'stripe_customer_id'),
            'stripe_subscription_status' => 'active',
        ], fn ($value) => $value !== null && $value !== ''));

        if ($server->status === ServerStatus::AwaitingPayment) {
            $server->forceFill([
                'status' => ServerStatus::Pending,
                'current_step' => 'Queued',
                'metadata' => $metadata,
            ])->save();
            $this->provision->execute($server->fresh());

            return $server->fresh();
        }

        $server->forceFill(['metadata' => $metadata])->save();

        return $server->fresh();
    }
}
