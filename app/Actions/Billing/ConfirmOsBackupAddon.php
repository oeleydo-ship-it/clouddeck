<?php

namespace App\Actions\Billing;

use App\Models\User;

/**
 * Applies a paid OS backup storage add-on from Stripe Checkout / subscription events.
 */
final class ConfirmOsBackupAddon
{
    /**
     * @param  array<string, mixed>  $checkoutSession
     */
    public function fromCheckoutSession(array $checkoutSession, bool $fromCompletedWebhook = false): ?User
    {
        if (data_get($checkoutSession, 'metadata.purpose') !== 'os_backup_storage') {
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
        if (! $user) {
            return null;
        }

        $gb = max(0, (int) data_get($checkoutSession, 'metadata.gb', 0));
        if ($gb < 1) {
            $gb = max(1, (int) data_get($checkoutSession, 'line_items.data.0.quantity', 0));
        }

        return $this->apply($user, $gb, [
            'stripe_subscription_id' => data_get($checkoutSession, 'subscription'),
            'stripe_customer_id' => data_get($checkoutSession, 'customer'),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function apply(User $user, int $gigabytes, array $fields): User
    {
        if ($customer = data_get($fields, 'stripe_customer_id')) {
            $user->stripe_customer_id = $customer;
        }

        $status = (string) (data_get($fields, 'status') ?: 'active');
        $active = in_array($status, ['active', 'trialing'], true);

        $user->forceFill([
            'os_backup_addon_gb' => $active ? max(0, $gigabytes) : 0,
            'os_backup_stripe_subscription_id' => data_get($fields, 'stripe_subscription_id') ?: $user->os_backup_stripe_subscription_id,
            'os_backup_stripe_subscription_status' => $status,
        ])->save();

        return $user->fresh();
    }
}
