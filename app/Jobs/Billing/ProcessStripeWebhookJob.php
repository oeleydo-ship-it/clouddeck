<?php

namespace App\Jobs\Billing;

use App\Billing\Stripe\StripeWebhookHandler;
use App\Models\BillingWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $eventId) {}

    public function handle(StripeWebhookHandler $handler): void
    {
        $event = BillingWebhookEvent::findOrFail($this->eventId);
        if ($event->status === 'processed') {
            return;
        }
        $event->increment('attempts');
        $handler->handle($event->payload);
        $event->update(['status' => 'processed', 'processed_at' => now(), 'failure_reason' => null]);
    }

    public function failed(Throwable $e): void
    {
        BillingWebhookEvent::find($this->eventId)?->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
    }
}
