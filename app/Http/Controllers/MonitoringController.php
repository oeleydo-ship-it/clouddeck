<?php

namespace App\Http\Controllers;

use App\Jobs\Monitoring\ManageMonitoringAgentJob;
use App\Models\AlertRule;
use App\Models\NotificationChannel;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public function rotate(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $secret = Str::random(64);
        $server->update(['monitoring_secret' => $secret, 'monitoring_enabled' => true]);
        $operation = $server->operations()->create(['user_id' => $request->user()->id, 'type' => 'monitoring:install', 'status' => 'pending']);
        ManageMonitoringAgentJob::dispatch($operation->id);

        return back()->with('monitoring_secret', $secret)->with('status', 'Monitoring credentials rotated and agent installation queued.');
    }

    public function disable(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $server->update(['monitoring_secret' => null, 'monitoring_enabled' => false, 'last_seen_at' => null]);
        $operation = $server->operations()->create(['user_id' => $request->user()->id, 'type' => 'monitoring:remove', 'status' => 'pending']);
        ManageMonitoringAgentJob::dispatch($operation->id);

        return back()->with('status', 'Monitoring disabled, its secret revoked, and agent removal queued.');
    }

    public function storeRule(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'metric' => ['required', Rule::in(['cpu_percent', 'memory_percent', 'disk_percent', 'load_average', 'server_offline'])],
            'operator' => ['required', Rule::in(['gt', 'gte', 'lt', 'lte'])],
            'threshold' => ['required', 'numeric', 'between:0,100000'],
            'consecutive_samples' => ['required', 'integer', 'between:1,12'],
            'cooldown_minutes' => ['required', 'integer', 'between:5,1440'],
            'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
        ]);
        $server->alertRules()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Alert rule created.');
    }

    public function destroyRule(Request $request, AlertRule $alertRule): RedirectResponse
    {
        abort_unless($alertRule->user_id === $request->user()->id, 404);
        $alertRule->delete();

        return back()->with('status', 'Alert rule removed.');
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // The address is optional so alerts can go to a shared mailbox rather than the
            // account holder, which is how most teams actually want to read them.
            'address' => ['nullable', 'email', 'max:190'],
            'events' => ['sometimes', 'array'],
            'events.*' => [Rule::in(array_keys(NotificationChannel::EVENTS))],
        ]);

        $request->user()->notificationChannels()->create([
            'name' => $data['name'],
            'type' => 'email',
            'configuration' => filled($data['address'] ?? null) ? ['address' => $data['address']] : [],
            // Empty means every event, which is also what a recipient created before events
            // existed carries, so the two behave the same.
            'events' => $data['events'] ?? [],
        ]);

        return back()->with('status', 'Email recipient added.');
    }

    public function destroyChannel(Request $request, NotificationChannel $notificationChannel): RedirectResponse
    {
        abort_unless($notificationChannel->user_id === $request->user()->id, 404);
        $notificationChannel->delete();

        return back()->with('status', 'Email recipient removed.');
    }
}
