<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ConfirmOsBackupAddon;
use App\Billing\Stripe\StripeClient;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use App\Services\QuotaManager;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class BillingController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements, QuotaManager $quotas, SystemSettings $settings)
    {
        $managedServersEnabled = $settings->managedServersEnabled();
        $resources = $managedServersEnabled
            ? ['servers', 'managed_servers', 'sites', 'managed_sites', 'databases', 'api_tokens', 'teams', 'team_members', 'os_backup_gb']
            : ['servers', 'sites', 'databases', 'api_tokens', 'teams', 'team_members', 'os_backup_gb'];
        $plan = $entitlements->plan($request->user());
        $user = $request->user();
        $addonActive = in_array($user->os_backup_stripe_subscription_status, ['active', 'trialing'], true);

        return Inertia::render('Billing/Index', [
            'title' => 'Billing',
            'empty' => 'No plans are available yet',
            'plan' => $plan,
            'subscription' => $entitlements->subscription($user),
            'plans' => Plan::where('active', true)->where('public', true)->orderBy('sort_order')->get()->map(fn (Plan $available) => [
                ...$available->toArray(),
                'monthly_price_label' => $available->formattedPrice('monthly_price'),
                'yearly_price_label' => $available->yearly_price ? $available->formattedPrice('yearly_price') : null,
                'quota_lines' => $available->quotaLines($managedServersEnabled),
                'feature_labels' => $available->enabledFeatureLabels(),
                'unlimited' => collect($available->limits ?? [])->contains(fn ($limit) => $limit < 0) ? 'Unlimited' : null,
            ]),
            'usage' => collect($resources)->mapWithKeys(function ($resource) use ($quotas, $entitlements, $user) {
                $used = $quotas->usage($user, $resource);
                $limit = $entitlements->limit($user, $resource);

                $planLimit = $entitlements->planLimit($user, $resource);

                return [$resource => [
                    'used' => $used,
                    'limit' => $limit,
                    'plan_limit' => $planLimit,
                    'label' => $planLimit < 0 ? 'Unlimited' : ($used.' / '.$planLimit),
                ]];
            })->all(),
            'requests' => $user->billingRequests()->with('plan')->latest()->limit(10)->get(),
            'invoices' => $user->billingInvoices()->latest()->limit(20)->get(),
            'stripeEnabled' => (bool) config('services.stripe.secret'),
            'managedServersEnabled' => $managedServersEnabled,
            'osBackupGbPriceCents' => $settings->osBackupGbPriceCents(),
            'osBackupAddonGb' => $addonActive ? (int) $user->os_backup_addon_gb : 0,
            'osBackupAddonActive' => $addonActive,
            'osBackupTitle' => 'OS backup storage',
            'osBackupCta' => 'Buy with Stripe',
            'checkoutLabel' => 'Pay & subscribe',
            'requestLabel' => 'Request this plan',
            'currentPlanLabel' => 'Current plan:',
        ]);
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

    public function checkoutOsBackup(Request $request, StripeClient $stripe, SystemSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'gigabytes' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        if (! config('services.stripe.secret')) {
            throw ValidationException::withMessages(['billing' => 'Stripe billing is not configured.']);
        }

        $user = $request->user();
        if (in_array($user->os_backup_stripe_subscription_status, ['active', 'trialing'], true) && filled($user->os_backup_stripe_subscription_id)) {
            throw ValidationException::withMessages([
                'billing' => 'You already have an OS backup storage add-on. Open the Stripe portal to change quantity or cancel, then purchase again if needed.',
            ]);
        }

        try {
            $session = $stripe->checkoutOsBackupAddon($user, (int) $data['gigabytes'], $settings->osBackupGbPriceCents());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        }

        $audit->record($request, 'billing.os-backup-addon-checkout', $user, [], [
            'gigabytes' => (int) $data['gigabytes'],
            'unit_cents' => $settings->osBackupGbPriceCents(),
        ]);

        return redirect()->away($session['url']);
    }

    public function osBackupSuccess(Request $request, StripeClient $stripe, ConfirmOsBackupAddon $confirm): RedirectResponse
    {
        $sessionId = (string) $request->query('session_id', '');
        if ($sessionId !== '') {
            try {
                $confirm->fromCheckoutSession($stripe->checkoutSession($sessionId));
            } catch (RuntimeException) {
                // Webhook may still confirm; show a soft success either way.
            }
        }

        return redirect()->route('billing.index')->with('status', 'OS backup storage checkout completed. Capacity updates as soon as Stripe confirms the subscription.');
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
        return redirect()->route('billing.index')->with('status', 'Checkout completed. Your plan updates as soon as Stripe confirms the subscription.');
    }
}
