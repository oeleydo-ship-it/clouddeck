<?php

namespace App\Notifications;

use App\Models\BillingInvoice;
use App\Services\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingPaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $invoiceId)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = BillingInvoice::find($this->invoiceId);
        $platform = app(SystemSettings::class)->branding()['name'];

        return (new MailMessage)
            ->subject($platform.' payment failed')
            ->error()
            ->line('Stripe could not collect payment for your '.$platform.' subscription.')
            ->line($invoice ? "Invoice {$invoice->number}: {$invoice->currency} ".number_format($invoice->total / 100, 2) : 'Open your billing portal to review the invoice.')
            ->action('Manage billing', route('billing.index'));
    }

    public function toArray(object $notifiable): array
    {
        return ['invoice_id' => $this->invoiceId, 'message' => 'Subscription payment failed. Update your billing method.'];
    }
}
