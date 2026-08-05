<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Monitoring\AutoHealServicesJob;
use App\Jobs\Operations\RunServerOperationJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoHealServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_heal_does_nothing_when_service_is_down(): void
    {
        Queue::fake();
        Notification::fake();
        [, $server] = $this->readyServer(['auto_heal_enabled' => false]);
        $metric = $this->metric($server, ['nginx' => false, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true]);
        $this->metric($server, ['nginx' => false, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true], now()->addMinute());

        (new AutoHealServicesJob($metric->id))->handle();

        $this->assertDatabaseMissing('server_operations', ['server_id' => $server->id, 'type' => 'nginx:restart']);
        Queue::assertNotPushed(RunServerOperationJob::class);
        Notification::assertNothingSent();
    }

    public function test_consecutive_down_samples_queue_restart_and_notification(): void
    {
        Queue::fake();
        Notification::fake();
        [$user, $server] = $this->readyServer();
        $down = ['nginx' => false, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true];
        $this->metric($server, $down, now()->subMinute());
        $latest = $this->metric($server, $down);

        (new AutoHealServicesJob($latest->id))->handle();

        $this->assertDatabaseHas('server_operations', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'type' => 'nginx:restart',
            'target' => 'auto-heal:nginx',
            'status' => 'pending',
        ]);
        Queue::assertPushedOn('operations', RunServerOperationJob::class);
        Notification::assertSentTo($user, OperationalEventNotification::class, function (OperationalEventNotification $notification) {
            return $notification->event === 'auto_heal'
                && str_contains($notification->title, 'Auto-heal queued')
                && str_contains($notification->body, 'nginx');
        });
        $this->assertArrayHasKey('nginx', $server->fresh()->auto_heal_last_actions);
    }

    public function test_cooldown_suppresses_a_second_queue(): void
    {
        Queue::fake();
        Notification::fake();
        [, $server] = $this->readyServer([
            'auto_heal_last_actions' => ['nginx' => now()->subMinutes(5)->toIso8601String()],
            'auto_heal_cooldown_minutes' => 15,
        ]);
        $down = ['nginx' => false, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true];
        $this->metric($server, $down, now()->subMinute());
        $latest = $this->metric($server, $down);

        (new AutoHealServicesJob($latest->id))->handle();

        $this->assertDatabaseMissing('server_operations', ['server_id' => $server->id, 'type' => 'nginx:restart']);
        Queue::assertNotPushed(RunServerOperationJob::class);
    }

    public function test_pending_operation_of_same_type_suppresses_duplicate(): void
    {
        Queue::fake();
        Notification::fake();
        [$user, $server] = $this->readyServer();
        $server->operations()->create([
            'user_id' => $user->id,
            'type' => 'nginx:restart',
            'target' => 'nginx',
            'status' => 'pending',
        ]);
        $down = ['nginx' => false, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true];
        $this->metric($server, $down, now()->subMinute());
        $latest = $this->metric($server, $down);

        (new AutoHealServicesJob($latest->id))->handle();

        $this->assertSame(1, $server->operations()->where('type', 'nginx:restart')->count());
        Queue::assertNotPushed(RunServerOperationJob::class);
    }

    public function test_up_sample_does_not_heal(): void
    {
        Queue::fake();
        Notification::fake();
        [, $server] = $this->readyServer();
        $up = ['nginx' => true, 'php_fpm' => true, 'mysql' => true, 'redis' => true, 'supervisor' => true];
        $this->metric($server, $up, now()->subMinute());
        $latest = $this->metric($server, $up);

        (new AutoHealServicesJob($latest->id))->handle();

        $this->assertDatabaseMissing('server_operations', ['server_id' => $server->id]);
        Queue::assertNotPushed(RunServerOperationJob::class);
    }

    public function test_metric_ingestion_dispatches_auto_heal_job(): void
    {
        Queue::fake();
        [, $server] = $this->readyServer();
        $body = json_encode([
            'cpu_percent' => 10,
            'memory_percent' => 20,
            'disk_percent' => 30,
            'load_average' => 0.5,
            'services' => ['nginx' => false],
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = str_repeat('c', 32);
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, 'shared-secret');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MONITORING_TIMESTAMP' => $timestamp,
            'HTTP_X_MONITORING_NONCE' => $nonce,
            'HTTP_X_MONITORING_SIGNATURE' => $signature,
        ];

        $this->call('POST', "/api/monitoring/{$server->id}/metrics", [], [], [], $headers, $body)->assertAccepted();

        Queue::assertPushedOn('monitoring', AutoHealServicesJob::class);
    }

    public function test_toggle_enable_and_disable_is_tenant_authorized(): void
    {
        [$owner, $server] = $this->readyServer(['auto_heal_enabled' => false]);
        $intruder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($intruder)->post("/servers/{$server->id}/auto-heal")->assertForbidden();
        $this->actingAs($owner)->post("/servers/{$server->id}/auto-heal")->assertSessionHas('status');
        $this->assertTrue($server->fresh()->auto_heal_enabled);

        $this->actingAs($intruder)->delete("/servers/{$server->id}/auto-heal")->assertForbidden();
        $this->actingAs($owner)->delete("/servers/{$server->id}/auto-heal")->assertSessionHas('status');
        $this->assertFalse($server->fresh()->auto_heal_enabled);
        $this->assertNull($server->fresh()->auto_heal_last_actions);
    }

    public function test_disabling_monitoring_clears_auto_heal_state(): void
    {
        Queue::fake();
        [$user, $server] = $this->readyServer([
            'auto_heal_last_actions' => ['nginx' => now()->toIso8601String()],
        ]);

        $this->actingAs($user)->delete("/servers/{$server->id}/monitoring")->assertSessionHas('status');

        $server->refresh();
        $this->assertFalse($server->monitoring_enabled);
        $this->assertFalse($server->auto_heal_enabled);
        $this->assertNull($server->auto_heal_last_actions);
    }

    public function test_enable_requires_monitoring(): void
    {
        [$user, $server] = $this->readyServer([
            'monitoring_enabled' => false,
            'monitoring_secret' => null,
            'auto_heal_enabled' => false,
        ]);

        $this->actingAs($user)->post("/servers/{$server->id}/auto-heal")->assertStatus(422);
        $this->assertFalse($server->fresh()->auto_heal_enabled);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Server}
     */
    private function readyServer(array $overrides = []): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create([
            'user_id' => $user->id,
            'provider' => 'digitalocean',
            'name' => 'Production',
            'credentials' => ['token' => 'secret'],
            'validated_at' => now(),
        ]);
        $server = Server::create([
            'user_id' => $user->id,
            'cloud_account_id' => $account->id,
            'name' => 'App',
            'hostname' => 'app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '192.0.2.10',
            'status' => ServerStatus::Ready,
            'monitoring_enabled' => true,
            'monitoring_secret' => 'shared-secret',
            'auto_heal_enabled' => true,
            'auto_heal_cooldown_minutes' => 15,
            'auto_heal_consecutive_samples' => 2,
            ...$overrides,
        ]);

        return [$user, $server];
    }

    /**
     * @param  array<string, bool>  $services
     */
    private function metric(Server $server, array $services, $recordedAt = null)
    {
        return $server->metrics()->create([
            'cpu_percent' => 10,
            'memory_percent' => 20,
            'disk_percent' => 30,
            'load_average' => 0.5,
            'services' => $services,
            'recorded_at' => $recordedAt ?? now(),
        ]);
    }
}
