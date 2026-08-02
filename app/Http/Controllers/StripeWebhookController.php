<?php

namespace App\Http\Controllers;

use App\Billing\Stripe\StripeWebhookVerifier;
use App\Jobs\Billing\ProcessStripeWebhookJob;
use App\Models\BillingWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookVerifier $verifier): JsonResponse
    {
        $payload = $request->getContent();
        $secret = (string) config('services.stripe.webhook_secret');
        if (! $secret || ! $verifier->valid($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }
        $event = json_decode($payload, true);
        if (! is_array($event) || ! data_get($event, 'id') || ! data_get($event, 'type')) {
            return response()->json(['message' => 'Invalid event.'], 400);
        }
        $record = BillingWebhookEvent::firstOrCreate(['provider_event_id' => data_get($event, 'id')], ['provider' => 'stripe', 'type' => data_get($event, 'type'), 'payload' => $event]);
        if ($record->wasRecentlyCreated) {
            ProcessStripeWebhookJob::dispatch($record->id)->onQueue('billing');
        }

        return response()->json(['received' => true]);
    }
}
