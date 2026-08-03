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
            'type' => ['required', Rule::in(['email', 'slack', 'discord', 'telegram', 'sms', 'push'])],
            'webhook_url' => ['nullable', 'url:https', 'max:500'],
            'bot_token' => ['nullable', 'string', 'max:200'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'account_sid' => ['nullable', 'string', 'max:100'],
            'auth_token' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'string', 'max:30'],
            'to' => ['nullable', 'string', 'max:30'],
            'app_token' => ['nullable', 'string', 'max:100'],
            'user_key' => ['nullable', 'string', 'max:100'],
            'events' => ['sometimes', 'array'],
            'events.*' => [Rule::in(array_keys(NotificationChannel::EVENTS))],
        ]);
        $configuration = match ($data['type']) {
            'email' => [],
            'slack', 'discord' => $this->validatedWebhook($data['type'], $data['webhook_url'] ?? ''),
            'telegram' => $this->validatedTelegram($data),
            'sms' => $this->validatedSms($data),
            'push' => $this->validatedPush($data),
        };
        $request->user()->notificationChannels()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'configuration' => $configuration,
            // Empty means every event, which is also what a channel created before events
            // existed carries, so the two behave the same.
            'events' => $data['events'] ?? [],
        ]);

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

    private function validatedSms(array $data): array
    {
        foreach (['account_sid', 'auth_token', 'from', 'to'] as $key) {
            abort_if(blank($data[$key] ?? null), 422, 'Twilio account SID, auth token, from, and to numbers are all required.');
        }
        // E.164, which is what Twilio accepts. A number in local format is rejected at send
        // time with an error nobody sees, so it is refused here instead.
        foreach (['from', 'to'] as $key) {
            abort_unless(preg_match('/^\+[1-9]\d{6,14}$/', $data[$key]), 422, 'Phone numbers must be in international format, such as +14155550123.');
        }

        return ['account_sid' => $data['account_sid'], 'auth_token' => $data['auth_token'], 'from' => $data['from'], 'to' => $data['to']];
    }

    private function validatedPush(array $data): array
    {
        abort_if(blank($data['app_token'] ?? null) || blank($data['user_key'] ?? null), 422, 'A Pushover application token and user key are required.');

        return ['app_token' => $data['app_token'], 'user_key' => $data['user_key']];
    }
}
