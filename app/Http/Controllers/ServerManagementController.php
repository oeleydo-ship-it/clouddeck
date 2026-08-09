<?php

namespace App\Http\Controllers;

use App\Actions\Servers\ConfirmManagedServerPayment;
use App\Billing\Stripe\StripeClient;
use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Models\AlertIncident;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Services\AuditLogger;
use App\Services\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ServerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $servers = $request->user()->accessibleServers()
            ->with(['sites', 'latestMetric', 'cloudAccount', 'team'])
            ->latest()
            ->paginate(15);

        $monitored = $request->user()->accessibleServers()->where('monitoring_enabled', true)->get(['id', 'last_seen_at']);
        $reachable = $monitored->filter(fn ($server) => $server->last_seen_at && $server->last_seen_at->gte(now()->subMinutes(5)))->count();

        // Summary strip above the table. Every figure comes from the same rows the table
        // renders or from the agent's own samples, so the two can never disagree.
        $cpuAverage = $monitored->isEmpty() ? null : ServerMetric::query()
            ->whereIn('server_id', $monitored->pluck('id'))
            ->where('recorded_at', '>=', now()->subDay())
            ->avg('cpu_percent');

        return view('servers.index', [
            'servers' => $servers,
            'summary' => [
                'total' => $servers->total(),
                'uptime' => $monitored->isEmpty() ? null : round($reachable / $monitored->count() * 100, 2),
                'cpu' => $cpuAverage === null ? null : round((float) $cpuAverage, 1),
                'alerts' => AlertIncident::query()
                    ->where('status', 'open')
                    ->whereHas('server', fn ($query) => $query->accessibleTo($request->user()))
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, Server $server, TeamAccess $teams): View
    {
        $this->authorize('view', $server);

        return view('servers.manage', [
            'server' => $server->load([
                'databases.backups',
                'databases.site',
                'cronJobs',
                'operations' => fn ($q) => $q->latest()->limit(20),
                'sites.queueWorkers',
                'metrics' => fn ($q) => $q->latest('recorded_at')->limit(72),
                'alertRules',
                'alertIncidents' => fn ($q) => $q->latest('started_at')->limit(5),
                'backupPolicies.database',
                'backupPolicies.databaseBackups' => fn ($q) => $q->latest()->limit(1),
                'backupPolicies.snapshots' => fn ($q) => $q->latest()->limit(1),
                'snapshots' => fn ($q) => $q->latest()->limit(30),
            ]),
            'backupDiskOptions' => app(\App\Services\BackupStorage::class)->privateDiskOptions(),
            'transferTeams' => $request->user()->teamMemberships()->with('team')->whereNotNull('accepted_at')->get()->filter(fn ($membership) => $teams->canManage($request->user(), $membership->team))->pluck('team'),
        ]);
    }

    public function checkoutSuccess(Request $request, Server $server, StripeClient $stripe, ConfirmManagedServerPayment $confirm): RedirectResponse
    {
        $this->authorize('view', $server);

        $sessionId = (string) $request->query('session_id', '');
        if ($sessionId !== '' && $server->status === ServerStatus::AwaitingPayment && config('services.stripe.secret')) {
            try {
                $session = $stripe->checkoutSession($sessionId);
                // Bind return URL to this server; reject sessions for a different managed host.
                if ((string) data_get($session, 'metadata.server_id') === (string) $server->id) {
                    $confirm->fromCheckoutSession($session);
                    $server->refresh();
                }
            } catch (Throwable) {
                // Fall through: webhook may still confirm payment shortly.
            }
        }

        if ($server->status !== ServerStatus::AwaitingPayment) {
            return redirect()->route('servers.manage', $server)->with(
                'status',
                'Payment confirmed. Managed server provisioning has started.'
            );
        }

        return redirect()->route('servers.manage', $server)->with(
            'status',
            'Payment received. If provisioning does not start within a minute, open Complete payment again or wait for Stripe webhook confirmation.'
        );
    }

    public function checkout(Request $request, Server $server, StripeClient $stripe, ConfirmManagedServerPayment $confirm): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($server->isManaged() && $server->status === ServerStatus::AwaitingPayment, 422, 'This server is not awaiting payment.');

        // If Checkout already succeeded (webhook missed, e.g. local), confirm from the stored session instead of charging again.
        if ($existingSessionId = data_get($server->metadata, 'stripe_checkout_session_id')) {
            try {
                $existing = $stripe->checkoutSession((string) $existingSessionId);
                if ((string) data_get($existing, 'metadata.server_id') === (string) $server->id
                    && in_array((string) data_get($existing, 'payment_status'), ['paid', 'no_payment_required'], true)) {
                    $confirm->fromCheckoutSession($existing);

                    return redirect()->route('servers.manage', $server)->with(
                        'status',
                        'Payment already confirmed. Managed server provisioning has started.'
                    );
                }
            } catch (Throwable) {
                // Create a fresh Checkout session below.
            }
        }

        $amountCents = (int) round(((float) data_get($server->metadata, 'customer_price_monthly', 0)) * 100);
        if ($amountCents < 50) {
            throw ValidationException::withMessages(['billing' => 'Managed server price must be at least $0.50/mo.']);
        }
        if (! config('services.stripe.secret')) {
            throw ValidationException::withMessages(['billing' => 'Stripe billing is not configured.']);
        }

        try {
            $platform = app(\App\Services\SystemSettings::class)->branding()['name'];
            $session = $stripe->checkoutManagedServer($request->user(), $server, $amountCents, $platform.' managed server · '.$server->name);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        }

        $server->forceFill([
            'metadata' => array_merge($server->metadata ?? [], [
                'stripe_checkout_session_id' => $session['id'] ?? null,
            ]),
        ])->save();

        return redirect()->away($session['url']);
    }

    public function destroy(Request $request, Server $server, CloudProviderManager $providers, AuditLogger $audit, StripeClient $stripe): RedirectResponse
    {
        $this->authorize('delete', $server);
        $request->validate(['confirmation' => ['required', Rule::in([$server->hostname])]]);
        if ($server->sites()->exists()) {
            return back()->withErrors(['server' => 'Delete the attached sites before removing this server.']);
        }

        if ($subscriptionId = data_get($server->metadata, 'stripe_subscription_id')) {
            try {
                $stripe->cancelSubscription((string) $subscriptionId);
            } catch (Throwable $e) {
                return back()->withErrors(['server' => 'Unable to cancel the managed server subscription before delete: '.$e->getMessage()]);
            }
        }

        if ($server->provider_id) {
            try {
                $providers->forServer($server)->deleteServer($server->provider_id);
            } catch (Throwable $e) {
                return back()->withErrors(['server' => 'Unable to remove the provider Droplet: '.$e->getMessage()]);
            }
        }

        $audit->record($request, 'server.deleted', $server, ['hostname' => $server->hostname, 'provider_id' => $server->provider_id], []);
        $server->delete();

        return redirect()->route('dashboard')->with('status', 'Server removed.');
    }
}
