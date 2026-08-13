<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\Operations\RefreshFirewallStatusJob;
use App\Jobs\Operations\SyncFirewallRuleJob;
use App\Models\FirewallRule;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FirewallController extends Controller
{
    /**
     * Named UFW application profiles we allow instead of numeric ports.
     * Keep this narrow so user input never becomes an arbitrary ufw argument.
     */
    private const NAMED_PORTS = [
        'OpenSSH',
        'Nginx Full',
        'Nginx HTTP',
        'Nginx HTTPS',
    ];

    public function index(Request $request)
    {
        $servers = $request->user()->accessibleServers()
            ->orderBy('name')
            ->get();

        $selected = null;
        if ($request->filled('server')) {
            $selected = $servers->firstWhere('id', $request->string('server')->toString());
        }
        $selected ??= $servers->first();

        $rules = collect();
        if ($selected) {
            $this->authorize('view', $selected);
            $rules = $selected->firewallRules()->latest()->get();
        }

        return Inertia::render('Firewall/Index', [
            'title' => 'Firewall',
            'servers' => $servers,
            'selected' => $selected,
            'rules' => $rules,
            'namedPorts' => self::NAMED_PORTS,
            'lastSyncLabel' => 'Last sync',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRule($request);
        $server = Server::query()->findOrFail($data['server_id']);
        $this->authorize('update', $server);

        if ($server->status !== ServerStatus::Ready) {
            return back()->withErrors(['server_id' => 'Firewall rules can only be applied once the server is ready.'])->withInput();
        }

        $rule = $server->firewallRules()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'protocol' => $data['protocol'],
            'port' => $data['port'] ?: null,
            'from_ip' => $data['from_ip'] ?: null,
            'description' => $data['description'] ?: null,
            'status' => 'pending',
        ]);

        SyncFirewallRuleJob::dispatch($rule->id)->onQueue('operations');

        return redirect()
            ->route('firewall.index', ['server' => $server->id])
            ->with('status', 'Firewall rule queued for apply.');
    }

    public function destroy(Request $request, FirewallRule $firewallRule): RedirectResponse
    {
        $this->authorize('update', $firewallRule->server);

        $serverId = $firewallRule->server_id;
        $firewallRule->update(['status' => 'pending', 'status_message' => null]);
        $firewallRule->delete();
        SyncFirewallRuleJob::dispatch($firewallRule->id)->onQueue('operations');

        return redirect()
            ->route('firewall.index', ['server' => $serverId])
            ->with('status', 'Firewall rule removal queued.');
    }

    public function sync(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        if ($server->status !== ServerStatus::Ready) {
            return back()->withErrors(['sync' => 'Firewall sync requires a ready server.']);
        }

        $rules = $server->firewallRules()->get();
        if ($rules->isEmpty()) {
            return redirect()
                ->route('firewall.index', ['server' => $server->id])
                ->with('status', 'No firewall rules to apply. Add a rule first.');
        }

        foreach ($rules as $rule) {
            $rule->update(['status' => 'pending', 'status_message' => null]);
            SyncFirewallRuleJob::dispatch($rule->id)->onQueue('operations');
        }

        $server->update([
            'firewall_status' => 'pending',
            'firewall_message' => null,
        ]);

        return redirect()
            ->route('firewall.index', ['server' => $server->id])
            ->with('status', 'Applying '.$rules->count().' firewall '.Str::plural('rule', $rules->count()).' to the server.');
    }

    public function refresh(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        if ($server->status !== ServerStatus::Ready) {
            return back()->withErrors(['refresh' => 'Firewall status can only be refreshed on a ready server.']);
        }

        RefreshFirewallStatusJob::dispatch($server->id)->onQueue('operations');

        return redirect()
            ->route('firewall.index', ['server' => $server->id])
            ->with('status', 'Fetching remote UFW status.');
    }

    /**
     * @return array{server_id: string, type: string, protocol: string, port: ?string, from_ip: ?string, description: ?string}
     */
    private function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'server_id' => ['required', 'uuid', Rule::exists('servers', 'id')],
            'type' => ['required', Rule::in(['allow', 'deny'])],
            'protocol' => ['required', Rule::in(['tcp', 'udp', 'any'])],
            'port' => ['nullable', 'string', 'max:40'],
            'from_ip' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $port = filled($data['port'] ?? null) ? trim((string) $data['port']) : null;
        $fromIp = filled($data['from_ip'] ?? null) ? trim((string) $data['from_ip']) : null;

        if ($port === null && $fromIp === null) {
            throw ValidationException::withMessages([
                'port' => 'Provide a port (or named profile) and/or a source IP.',
            ]);
        }

        if ($port !== null) {
            $this->assertValidPort($port);
        }

        if ($fromIp !== null) {
            $this->assertValidCidrOrIp($fromIp);
        }

        if ($port !== null && ! preg_match('/^\d{1,5}(:\d{1,5})?$/', $port) && $fromIp !== null) {
            throw ValidationException::withMessages([
                'from_ip' => 'Source IP cannot be combined with a named UFW profile. Use a numeric port instead.',
            ]);
        }

        $data['port'] = $port;
        $data['from_ip'] = $fromIp;
        $data['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;

        return $data;
    }

    private function assertValidPort(string $port): void
    {
        if (in_array($port, self::NAMED_PORTS, true)) {
            return;
        }

        if (! preg_match('/^(\d{1,5})(?::(\d{1,5}))?$/', $port, $matches)) {
            throw ValidationException::withMessages([
                'port' => 'Use a port (80), range (8000:8100), or a known profile: '.implode(', ', self::NAMED_PORTS).'.',
            ]);
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) ? (int) $matches[2] : $start;

        if ($start < 1 || $start > 65535 || $end < 1 || $end > 65535 || $end < $start) {
            throw ValidationException::withMessages([
                'port' => 'Ports must be between 1 and 65535, and ranges must go low to high.',
            ]);
        }
    }

    private function assertValidCidrOrIp(string $value): void
    {
        if (str_contains($value, '/')) {
            [$ip, $mask] = array_pad(explode('/', $value, 2), 2, null);
            $maxMask = filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;

            if (! filter_var((string) $ip, FILTER_VALIDATE_IP) || ! ctype_digit((string) $mask) || (int) $mask < 0 || (int) $mask > $maxMask) {
                throw ValidationException::withMessages([
                    'from_ip' => 'Enter a valid IPv4/IPv6 address or CIDR (for example 203.0.113.10 or 10.0.0.0/24).',
                ]);
            }

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages([
                'from_ip' => 'Enter a valid IPv4/IPv6 address or CIDR (for example 203.0.113.10 or 10.0.0.0/24).',
            ]);
        }
    }
}
