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
            'type' => ['required', Rule::in(['email', 'slack', 'discord', 'telegram'])],
            'webhook_url' => ['nullable', 'url:https', 'max:500'],
            'bot_token' => ['nullable', 'string', 'max:200'],
            'chat_id' => ['nullable', 'string', 'max:100'],
        ]);
        $configuration = match ($data['type']) {
            'email' => [],
            'slack', 'discord' => $this->validatedWebhook($data['type'], $data['webhook_url'] ?? ''),
            'telegram' => $this->validatedTelegram($data),
        };
        $request->user()->notificationChannels()->create(['name' => $data['name'], 'type' => $data['type'], 'configuration' => $configuration]);

        return back()->with('status', 'Notification channel added.');
    }

    public function destroyChannel(Request $request, NotificationChannel $notificationChannel): RedirectResponse
    {
        abort_unless($notificationChannel->user_id === $request->user()->id, 404);
        $notificationChannel->delete();

        return back()->with('status', 'Notification channel removed.');
    }

    private function validatedWebhook(string $type, string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = $type === 'slack' ? ['hooks.slack.com'] : ['discord.com', 'discordapp.com'];
        abort_unless(in_array($host, $allowed, true), 422, 'Use an official '.$type.' webhook URL.');

        return ['webhook_url' => $url];
    }

    private function validatedTelegram(array $data): array
    {
        abort_if(blank($data['bot_token'] ?? null) || blank($data['chat_id'] ?? null), 422, 'Telegram bot token and chat ID are required.');

        return ['bot_token' => $data['bot_token'], 'chat_id' => $data['chat_id']];
    }
}
