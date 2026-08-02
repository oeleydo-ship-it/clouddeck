<?php

namespace App\Billing\Contracts;

use App\Models\BillingRequest;
use App\Models\Subscription;

interface BillingGateway
{
    public function activate(BillingRequest $billingRequest, int $periodDays): Subscription;
}
