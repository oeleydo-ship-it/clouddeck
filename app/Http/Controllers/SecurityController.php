<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\Operations\SyncFirewallRuleJob;
use App\Jobs\Security\CollectServerSecuritySignalsJob;
use App\Models\SecurityIncident;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\SecurityDetectionSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request, SecurityDetectionSettings $settings): View
    {
        $resolved = $settings->forUser($request->user());
        $servers = $settings->serverQueryFor($request->user())->withCount('sites')->orderBy('name')->get();
        $protected = $servers->where('status', ServerStatus::Ready)->whereNotNull('ssh_key_id');
        $incidents = SecurityIncident::query()->accessibleTo($request->user());

        return view('security.index', [
            'servers' => $servers,
            'rules' => collect($resolved['rules'])->map(fn (array $rule, string $key) => [
                'key' => $key,
                'single_event' => (int) (config('security-detection.rules', [])[$key]['threshold'] ?? 1) === 1,
                ...$rule,
            ])->values(),
            'detectionEnabled' => $resolved['enabled'],
            'settingsScope' => $resolved['scope']['label'],
            'canManageSettings' => $settings->canManage($request->user()),
            'protectedServers' => $protected->count(),
            'protectedSites' => $protected->sum('sites_count'),
            'openCritical' => (clone $incidents)->whereIn('status', ['open', 'acknowledged'])->where('severity', 'critical')->count(),
            'lastScan' => $protected->max('security_scanned_at') ?? $servers->max('security_scanned_at'),
        ]);
    }

    public function scan(Request $request, SecurityDetectionSettings $settings): RedirectResponse
    {
        if (! $settings->forUser($request->user())['enabled']) {
            throw ValidationException::withMessages(['scan' => 'Enable security detection before starting a scan.']);
        }

        $data = $request->validate(['server_id' => ['nullable', 'uuid']]);
        $servers = $settings->serverQueryFor($request->user())
            ->when($data['server_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->where('status', ServerStatus::Ready)
            ->whereNotNull('ssh_key_id')
            ->get();

        if (($data['server_id'] ?? null) && $servers->isEmpty()) {
            abort(404);
        }

        foreach ($servers as $server) {
            $this->authorize('update', $server);
            $server->markSecurityScan('queued');
            CollectServerSecuritySignalsJob::dispatch($server->id)->onQueue('operations');
        }

        return back()->with('status', 'Queued '.$servers->count().' security '.str('scan')->plural($servers->count()).'.');
    }

    public function scanStatus(Request $request, SecurityDetectionSettings $settings): JsonResponse
    {
        $servers = $settings->serverQueryFor($request->user())->withCount('sites')->orderBy('name')->get();

        return response()->json($this->scanStatusPayload($request, $servers));
    }

    public function settings(Request $request, SecurityDetectionSettings $settings, AuditLogger $audit): RedirectResponse
    {
        abort_unless($settings->canManage($request->user()), 403);
        $ruleKeys = array_keys(config('security-detection.rules', []));
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'rules' => ['required', 'array', 'size:'.count($ruleKeys)],
            'rules.*.key' => ['required', 'string', Rule::in($ruleKeys), 'distinct'],
            'rules.*.enabled' => ['required', 'boolean'],
            'rules.*.threshold' => ['required', 'integer', 'between:1,10000'],
            'rules.*.lookback_minutes' => ['required', 'integer', 'between:1,1440'],
            'rules.*.severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
        ]);

        $overrides = collect($data['rules'])->mapWithKeys(function (array $rule): array {
            $defaults = config('security-detection.rules', [])[$rule['key']] ?? [];
            $threshold = (int) ($defaults['threshold'] ?? 1) === 1 ? 1 : (int) $rule['threshold'];

            return [$rule['key'] => [
                'enabled' => (bool) $rule['enabled'],
                'threshold' => $threshold,
                'lookback_minutes' => (int) $rule['lookback_minutes'],
                'severity' => $rule['severity'],
            ]];
        })->all();

        $old = Arr::except($settings->forUser($request->user()), ['model']);
        $setting = $settings->saveFor($request->user(), (bool) $data['enabled'], $overrides);
        $new = Arr::except($settings->forUser($request->user()), ['model']);
        $audit->record($request, 'security_detection.settings_updated', $setting, $old, $new);

        return back()->with('status', 'Security detection settings saved for '.$new['scope']['label'].'.');
    }

    public function resetSettings(Request $request, SecurityDetectionSettings $settings, AuditLogger $audit): RedirectResponse
    {
        abort_unless($settings->canManage($request->user()), 403);
        $old = Arr::except($settings->forUser($request->user()), ['model']);
        $setting = $settings->resetFor($request->user());
        $new = Arr::except($settings->forUser($request->user()), ['model']);
        $audit->record($request, 'security_detection.settings_reset', $setting, $old, $new);

        return back()->with('status', 'Recommended security detection defaults restored.');
    }

    public function status(Request $request, SecurityIncident $securityIncident, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeIncident($request, $securityIncident);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'acknowledged', 'resolved'])]]);
        $old = ['status' => $securityIncident->status];
        $attributes = [
            'status' => $data['status'],
            'acknowledged_by' => $data['status'] === 'acknowledged' ? $request->user()->id : null,
            'acknowledged_at' => $data['status'] === 'acknowledged' ? now() : null,
            'resolved_by' => $data['status'] === 'resolved' ? $request->user()->id : null,
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ];
        $securityIncident->update($attributes);
        $audit->record($request, 'security_incident.status_changed', $securityIncident, $old, ['status' => $data['status']]);

        return back()->with('status', 'Security incident marked '.$data['status'].'.');
    }

    public function block(Request $request, SecurityIncident $securityIncident, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeIncident($request, $securityIncident);
        $request->validate(['confirm' => ['accepted']]);

        $ip = $securityIncident->source_ip;
        $server = $securityIncident->server;
        if (! $ip || ! $server || ! $this->isSafePublicIp($ip) || $ip === $server->public_ip) {
            throw ValidationException::withMessages(['mitigation' => 'Only a public source IP that is not the server address can be blocked.']);
        }

        if ($securityIncident->firewallRule) {
            return back()->with('status', 'This incident IP already has a managed firewall rule.');
        }

        $rule = $server->firewallRules()->create([
            'user_id' => $request->user()->id,
            'type' => 'deny',
            'protocol' => 'any',
            'port' => null,
            'from_ip' => $ip,
            'description' => 'Security incident '.$securityIncident->id,
            'status' => 'pending',
        ]);
        $securityIncident->update([
            'firewall_rule_id' => $rule->id,
            'mitigation_status' => 'pending',
            'mitigation_action' => 'block_ip',
        ]);
        SyncFirewallRuleJob::dispatch($rule->id)->onQueue('operations');
        $audit->record($request, 'security_incident.ip_blocked', $securityIncident, [], ['source_ip' => $ip, 'firewall_rule_id' => $rule->id]);

        return back()->with('status', 'IP block queued through the server firewall.');
    }

    public function unblock(Request $request, SecurityIncident $securityIncident, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeIncident($request, $securityIncident);
        $request->validate(['confirm' => ['accepted']]);
        $rule = $securityIncident->firewallRule;
        if (! $rule || $rule->trashed()) {
            throw ValidationException::withMessages(['mitigation' => 'No active incident-managed firewall rule exists.']);
        }

        $rule->update(['status' => 'pending', 'status_message' => null]);
        $rule->delete();
        SyncFirewallRuleJob::dispatch($rule->id)->onQueue('operations');
        $securityIncident->update(['mitigation_status' => 'removing']);
        $audit->record($request, 'security_incident.ip_unblocked', $securityIncident, [], ['firewall_rule_id' => $rule->id]);

        return back()->with('status', 'IP unblock queued through the server firewall.');
    }

    private function authorizeIncident(Request $request, SecurityIncident $incident): void
    {
        abort_unless(SecurityIncident::query()->accessibleTo($request->user())->whereKey($incident->id)->exists(), 404);
        abort_unless($incident->server, 422);
        $this->authorize('update', $incident->server);
    }

    private function isSafePublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array{servers: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    private function scanStatusPayload(Request $request, Collection $servers): array
    {
        $protected = $servers->where('status', ServerStatus::Ready)->whereNotNull('ssh_key_id');
        $lastScan = $protected->max('security_scanned_at') ?? $servers->max('security_scanned_at');
        $openCritical = SecurityIncident::query()
            ->accessibleTo($request->user())
            ->whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'critical')
            ->count();

        return [
            'servers' => $servers->map(fn (Server $server) => [
                'id' => $server->id,
                'status' => $server->security_scan_status ?: 'idle',
                'message' => $server->security_scan_message,
                'busy' => $server->securityScanIsBusy(),
                'scanned_at' => $server->security_scanned_at?->toIso8601String(),
                'scanned_at_human' => $server->security_scanned_at?->diffForHumans() ?? 'never',
                'label' => $this->scanStatusLabel($server),
                'badge' => $this->scanStatusBadge($server),
            ])->values()->all(),
            'summary' => [
                'protected_servers' => $protected->count(),
                'protected_sites' => (int) $protected->sum('sites_count'),
                'open_critical' => $openCritical,
                'last_scan' => $lastScan?->toIso8601String(),
                'last_scan_human' => $lastScan?->diffForHumans() ?? 'Never',
            ],
        ];
    }

    private function scanStatusLabel(Server $server): string
    {
        if ($server->securityScanIsStale()) {
            return $server->security_scan_status === 'running'
                ? 'Scan stalled — check the operations queue worker'
                : 'Queued too long — is the operations worker running?';
        }

        return match ($server->security_scan_status) {
            'queued' => 'Queued',
            'running' => 'Scanning…',
            'failed' => 'Failed',
            'succeeded', 'idle', null => $server->security_scanned_at
                ? 'Last scan '.$server->security_scanned_at->diffForHumans()
                : 'Last scan never',
            default => 'Last scan never',
        };
    }

    private function scanStatusBadge(Server $server): string
    {
        if ($server->securityScanIsStale()) {
            return 'danger';
        }

        return match ($server->security_scan_status) {
            'queued' => 'warning',
            'running' => 'info',
            'failed' => 'danger',
            'succeeded' => 'success',
            default => 'neutral',
        };
    }
}
