<?php

namespace App\Billing;

use App\Billing\Contracts\BillingGateway;
use App\Models\BillingRequest;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

final class ManualBillingGateway implements BillingGateway
{
    public function activate(BillingRequest $billingRequest, int $periodDays): Subscription
    {
        return DB::transaction(function () use ($billingRequest, $periodDays): Subscription {
            $billingRequest->user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'ended', 'ended_at' => now()]);

            return $billingRequest->user->subscriptions()->create([
                'plan_id' => $billingRequest->plan_id,
                'status' => 'active',
                'provider' => 'manual',
                'current_period_ends_at' => now()->addDays($periodDays),
            ]);
        });
    }
}
