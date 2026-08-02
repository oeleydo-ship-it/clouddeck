<?php

namespace Tests\Feature;

use App\Jobs\Monitoring\CheckOfflineServersJob;
use App\Jobs\Monitoring\DeliverAlertChannelsJob;
use App\Jobs\Monitoring\EvaluateMetricAlertsJob;
use App\Jobs\Monitoring\ManageMonitoringAgentJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\User;
use App\Notifications\AlertTriggeredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_secret_is_encrypted_and_only_returned_when_rotated(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)->post("/servers/{$server->id}/monitoring/rotate")->assertSessionHas('monitoring_secret');
        $secret = session('monitoring_secret');

        $this->assertTrue($server->fresh()->monitoring_enabled);
        $this->assertSame($secret, $server->fresh()->monitoring_secret);
        $this->assertStringNotContainsString($secret, DB::table('servers')->where('id', $server->id)->value('monitoring_secret'));
        $this->assertArrayNotHasKey('monitoring_secret', $server->fresh()->toArray());
        Queue::assertPushedOn('operations', ManageMonitoringAgentJob::class);
    }

    public function test_signed_metrics_are_accepted_once_and_alert_evaluation_is_queued(): void
    {
        Queue::fake();
        [, $server] = $this->infrastructure();
        $server->update(['monitoring_enabled' => true, 'monitoring_secret' => 'shared-secret']);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = str_repeat('a', 32);
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, 'shared-secret');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_MONITORING_TIMESTAMP' => $timestamp, 'HTTP_X_MONITORING_NONCE' => $nonce, 'HTTP_X_MONITORING_SIGNATURE' => $signature];

        $this->call('POST', "/api/monitoring/{$server->id}/metrics", [], [], [], $headers, $body)->assertAccepted();
        $this->call('POST', "/api/monitoring/{$server->id}/metrics", [], [], [], $headers, $body)->assertConflict();

        $this->assertDatabaseHas('server_metrics', ['server_id' => $server->id, 'cpu_percent' => 91.25]);
        $this->assertNotNull($server->fresh()->last_seen_at);
        Queue::assertPushedOn('monitoring', EvaluateMetricAlertsJob::class);
    }

    public function test_invalid_or_expired_metric_signatures_are_rejected(): void
    {
        [, $server] = $this->infrastructure();
        $server->update(['monitoring_enabled' => true, 'monitoring_secret' => 'shared-secret']);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);

        $this->call('POST', "/api/monitoring/{$server->id}/metrics", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_MONITORING_TIMESTAMP' => (string) now()->subMinutes(10)->timestamp, 'HTTP_X_MONITORING_NONCE' => str_repeat('b', 32), 'HTTP_X_MONITORING_SIGNATURE' => str_repeat('0', 64)], $body)->assertUnauthorized();
        $this->assertDatabaseEmpty('server_metrics');
    }

    public function test_consecutive_samples_open_and_normal_sample_resolves_incident(): void
    {
        Notification::fake();
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $rule = $server->alertRules()->create(['user_id' => $user->id, 'name' => 'High CPU', 'metric' => 'cpu_percent', 'operator' => 'gte', 'threshold' => 90, 'consecutive_samples' => 3, 'cooldown_minutes' => 30, 'severity' => 'critical']);
        foreach ([91, 92, 93] as $value) {
            $metric = $server->metrics()->create(['cpu_percent' => $value, 'memory_percent' => 40, 'disk_percent' => 50, 'load_average' => 1, 'recorded_at' => now()]);
        }

        (new EvaluateMetricAlertsJob($metric->id))->handle();

        $this->assertDatabaseHas('alert_incidents', ['alert_rule_id' => $rule->id, 'status' => 'open', 'severity' => 'critical']);
        Notification::assertSentTo($user, AlertTriggeredNotification::class);

        $normal = $server->metrics()->create(['cpu_percent' => 20, 'memory_percent' => 40, 'disk_percent' => 50, 'load_average' => 1, 'recorded_at' => now()->addMinute()]);
        (new EvaluateMetricAlertsJob($normal->id))->handle();
        $this->assertDatabaseHas('alert_incidents', ['alert_rule_id' => $rule->id, 'status' => 'resolved']);
    }

    public function test_offline_rule_creates_incident(): void
    {
        Notification::fake();
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $server->update(['monitoring_enabled' => true, 'monitoring_secret' => 'secret', 'last_seen_at' => now()->subMinutes(10)]);
        $rule = $server->alertRules()->create(['user_id' => $user->id, 'name' => 'Server offline', 'metric' => 'server_offline', 'operator' => 'gte', 'threshold' => 5, 'consecutive_samples' => 1, 'cooldown_minutes' => 30, 'severity' => 'critical']);

        (new CheckOfflineServersJob)->handle();

        $this->assertDatabaseHas('alert_incidents', ['alert_rule_id' => $rule->id, 'status' => 'open']);
    }

    public function test_notification_configuration_is_encrypted_and_webhooks_are_host_restricted(): void
    {
        [$user] = $this->infrastructure();
        $this->actingAs($user)->post('/notification-channels', ['name' => 'Slack operations', 'type' => 'slack', 'webhook_url' => 'https://hooks.slack.com/services/T/B/X'])->assertSessionHas('status');
        $channel = $user->notificationChannels()->firstOrFail();

        $this->assertSame('https://hooks.slack.com/services/T/B/X', $channel->configuration['webhook_url']);
        $this->assertStringNotContainsString('hooks.slack.com', DB::table('notification_channels')->where('id', $channel->id)->value('configuration'));
        $this->actingAs($user)->post('/notification-channels', ['name' => 'Unsafe', 'type' => 'slack', 'webhook_url' => 'https://127.0.0.1/internal'])->assertUnprocessable();
    }

    public function test_alert_channel_job_delivers_to_configured_webhook(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response([], 200)]);
        [$user, $server] = $this->infrastructure();
        $channel = $user->notificationChannels()->create(['name' => 'Slack', 'type' => 'slack', 'configuration' => ['webhook_url' => 'https://hooks.slack.com/services/T/B/X']]);
        $rule = $server->alertRules()->create(['user_id' => $user->id, 'name' => 'High CPU', 'metric' => 'cpu_percent', 'operator' => 'gte', 'threshold' => 90, 'consecutive_samples' => 1, 'cooldown_minutes' => 30, 'severity' => 'critical']);
        $incident = $server->alertIncidents()->create(['user_id' => $user->id, 'alert_rule_id' => $rule->id, 'status' => 'open', 'severity' => 'critical', 'metric' => 'cpu_percent', 'value' => 95, 'threshold' => 90, 'message' => 'High CPU', 'started_at' => now()]);

        (new DeliverAlertChannelsJob($incident->id, $channel->id))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/T/B/X' && $request['text'] === '[CRITICAL] App: High CPU (95)');
    }

    public function test_metric_history_and_rule_management_are_tenant_scoped(): void
    {
        [$owner, $server] = $this->infrastructure();
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($intruder, ['servers:read']);
        $this->getJson('/api/metrics?server_id='.$server->id)->assertNotFound();
        $this->actingAs($intruder)->post("/servers/{$server->id}/alert-rules", ['name' => 'High CPU', 'metric' => 'cpu_percent', 'operator' => 'gte', 'threshold' => 90, 'consecutive_samples' => 3, 'cooldown_minutes' => 30, 'severity' => 'warning'])->assertForbidden();
        $this->assertSame($owner->id, $server->user_id);
    }

    private function payload(): array
    {
        return ['cpu_percent' => 91.25, 'memory_percent' => 62.5, 'disk_percent' => 44, 'load_average' => 1.2, 'memory_used_bytes' => 1024, 'memory_total_bytes' => 2048, 'disk_used_bytes' => 4096, 'disk_total_bytes' => 8192, 'network_rx_bytes' => 100, 'network_tx_bytes' => 200, 'services' => ['nginx' => true], 'processes' => [['name' => 'php-fpm', 'cpu' => 5, 'memory' => 2]]];
    }

    private function infrastructure(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'name' => 'App', 'hostname' => 'app-01', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10']);

        return [$user, $server];
    }
}
