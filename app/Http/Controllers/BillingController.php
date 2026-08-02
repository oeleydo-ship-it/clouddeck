<?php

namespace App\Http\Controllers;

use App\Billing\Stripe\StripeClient;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use App\Services\QuotaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements, QuotaManager $quotas): View
    {
        $resources = ['servers', 'sites', 'databases', 'api_tokens', 'teams', 'team_members'];
        $plan = $entitlements->plan($request->user());

        return view('billing.index', ['plan' => $plan, 'subscription' => $entitlements->subscription($request->user()), 'plans' => Plan::where('active', true)->where('public', true)->orderBy('sort_order')->get(), 'usage' => collect($resources)->mapWithKeys(fn ($resource) => [$resource => ['used' => $quotas->usage($request->user(), $resource), 'limit' => $entitlements->limit($request->user(), $resource)]])->all(), 'requests' => $request->user()->billingRequests()->with('plan')->latest()->limit(10)->get(), 'invoices' => $request->user()->billingInvoices()->latest()->limit(20)->get(), 'stripeEnabled' => (bool) config('services.stripe.secret')]);
    }

    public function requestPlan(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('active', true)->where('public', true)], 'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])], 'customer_note' => ['nullable', 'string', 'max:1000']]);
        abort_if($request->user()->billingRequests()->where('status', 'pending')->exists(), 422, 'You already have a pending plan request.');
        $change = $request->user()->billingRequests()->create($data);
        $audit->record($request, 'billing.requested', $change, [], $data);

        return back()->with('status', 'Plan change request submitted for review.');
    }

    public function checkout(Request $request, StripeClient $stripe): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('active', true)->where('public', true)], 'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])]]);
        try {
            $session = $stripe->checkout($request->user(), Plan::findOrFail($data['plan_id']), $data['billing_cycle']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        }

        return redirect()->away($session['url']);
    }

    public function portal(Request $request, StripeClient $stripe): RedirectResponse
    {
        try {
            $session = $stripe->portal($request->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        }

        return redirect()->away($session['url']);
    }

    public function success(): RedirectResponse
    {
        return redirect()->route('billing.index')->with('status', 'Checkout completed. Subscription access updates after the signed Stripe webhook is processed.');
    }
}
