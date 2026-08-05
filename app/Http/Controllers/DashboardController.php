<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Models\Deployment;
use App\Models\Plan;
use App\Models\ServerMetric;
use App\Models\Site;
use App\Models\Subscription;
use App\Services\EntitlementService;
use App\Services\QuotaManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EntitlementService $entitlements, QuotaManager $quotas): View
    {
        $servers = $request->user()->accessibleServers();

        // Per-resource CPU/memory/disk live on each server's own page, not summarised here.
        $monitoredServers = (clone $servers)->where('monitoring_enabled', true)->get(['id', 'last_seen_at']);
        $offline = $monitoredServers->filter(fn ($server) => ! $server->last_seen_at || $server->last_seen_at->lt(now()->subMinutes(5)))->count();

        return view('dashboard', [
            'stats' => [
                'servers' => (clone $servers)->count(),
                'active' => (clone $servers)->whereIn('status', [ServerStatus::Active, ServerStatus::Ready])->count(),
                'sites' => Site::query()->whereHas('server', fn ($query) => $query->accessibleTo($request->user()))->count(),
                'deployments' => Deployment::whereHas('site.server', fn ($query) => $query->accessibleTo($request->user()))->whereDate('created_at', today())->count(),
                'failed' => Deployment::whereHas('site.server', fn ($query) => $query->accessibleTo($request->user()))->where('status', DeploymentStatus::Failed)->count(),
                'offline' => $offline,
            ],
            'recentServers' => (clone $servers)->with('cloudAccount')->latest()->limit(5)->get(),
            'recentDeployments' => Deployment::query()
                ->with(['site.server'])
                ->whereHas('site.server', fn ($query) => $query->accessibleTo($request->user()))
                ->latest()
                ->limit(5)
                ->get(),
            'health' => $this->health($monitoredServers->pluck('id')->all(), $monitoredServers->count(), $offline),
            'plan' => $this->plan($request, $entitlements, $quotas),
        ]);
    }

    /**
     * The plan panel. Usage comes from the same services the billing page uses, so the two
     * cannot drift into telling different stories about the same account.
     *
     * The upgrade itself deliberately stays on the billing page: whether an upgrade is a
     * Stripe checkout or a request an operator approves depends on configuration, and a
     * second copy of that decision here would be a second thing to keep correct.
     *
     * @return array{plan: Plan|null, subscription: Subscription|null, usage: array<string, array{used: int, limit: int}>, upgrade: Plan|null}
     */
    private function plan(Request $request, EntitlementService $entitlements, QuotaManager $quotas): array
    {
        $plan = $entitlements->plan($request->user());

        return [
            'plan' => $plan,
            'subscription' => $entitlements->subscription($request->user()),
            'usage' => collect(['servers', 'sites', 'databases'])
                ->mapWithKeys(fn (string $resource) => [$resource => [
                    'used' => $quotas->usage($request->user(), $resource),
                    'limit' => $entitlements->limit($request->user(), $resource),
                ]])
                ->all(),
            // The cheapest public plan that costs more than this one, so the panel can say
            // what upgrading actually means rather than pointing at a price list.
            'upgrade' => $plan
                ? Plan::where('active', true)->where('public', true)
                    ->where('monthly_price', '>', $plan->monthly_price)
                    ->orderBy('monthly_price')->first()
                : null,
        ];
    }

    /**
     * The fleet-wide numbers behind the Operational Health panel. Averaged over the last
     * 24 hours of agent samples rather than the newest reading alone, so one noisy minute
     * on one host does not redraw the whole picture. Null means "no agent has reported
     * yet", and the panel says so instead of drawing a confident zero.
     *
     * @param  array<int, string>  $serverIds
     * @return array{cpu: float|null, memory: float|null, uptime: float|null, samples: int}
     */
    private function health(array $serverIds, int $monitored, int $offline): array
    {
        if ($serverIds === []) {
            return ['cpu' => null, 'memory' => null, 'uptime' => null, 'samples' => 0];
        }

        $averages = ServerMetric::query()
            ->whereIn('server_id', $serverIds)
            ->where('recorded_at', '>=', now()->subDay())
            ->selectRaw('AVG(cpu_percent) as cpu_average, AVG(memory_percent) as memory_average, COUNT(*) as sample_count')
            ->first();

        return [
            'cpu' => $averages?->cpu_average === null ? null : round((float) $averages->cpu_average, 1),
            'memory' => $averages?->memory_average === null ? null : round((float) $averages->memory_average, 1),
            // Uptime here is agent reachability across the monitored fleet, the only uptime
            // figure this platform can measure without an external prober.
            'uptime' => $monitored === 0 ? null : round(($monitored - $offline) / $monitored * 100, 1),
            'samples' => (int) ($averages?->sample_count ?? 0),
        ];
    }
}
