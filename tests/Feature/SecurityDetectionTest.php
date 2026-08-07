<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Operations\SyncFirewallRuleJob;
use App\Jobs\Security\CollectServerSecuritySignalsJob;
use App\Jobs\Security\DispatchSecurityScansJob;
use App\Models\CloudAccount;
use App\Models\FirewallRule;
use App\Models\NotificationChannel;
use App\Models\SecurityIncident;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use App\Services\SecurityDetectionSettings;
use App\Services\SecurityDetectorEngine;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_ssh_events_reach_threshold_coalesce_and_redact_evidence(): void
    {
        Notification::fake();
        [$user, $server] = $this->infrastructure();
        $engine = app(SecurityDetectorEngine::class);

        $first = $engine->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'source' => 'auth',
            'source_ip' => '8.8.8.8',
            'count' => 5,
            'summary' => 'Failed SSH logins',
            'evidence' => ['attempts' => 5, 'token' => 'must-not-leak', 'line' => 'password=hunter2'],
        ]])->sole();

        $second = $engine->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'source_ip' => '8.8.8.8',
            'count' => 6,
            'evidence' => ['attempts' => 6],
        ]])->sole();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(11, $second->occurrence_count);
        $this->assertSame('[REDACTED]', $first->evidence['token']);
        $this->assertStringNotContainsString('hunter2', $first->evidence['line']);
        $this->assertSame(1, SecurityIncident::count());
    }

    public function test_security_page_and_incidents_are_tenant_scoped(): void
    {
        [$owner, $server] = $this->infrastructure('owner');
        [$other, $otherServer] = $this->infrastructure('other');
        $this->incident($server, 'Owner security incident');
        $this->incident($otherServer, 'Other security incident');

        $this->actingAs($owner)
            ->get('/security')
            ->assertOk()
            ->assertSee('Security')
            ->assertSee($server->name)
            ->assertDontSee($otherServer->name);

        $this->actingAs($owner)
            ->get('/notifications?tab=incidents&type=security')
            ->assertOk()
            ->assertSee('Owner security incident')
            ->assertDontSee('Other security incident');

        $this->actingAs($other)
            ->get('/notifications?tab=incidents&type=security')
            ->assertSee('Other security incident')
            ->assertDontSee('Owner security incident');
    }

    public function test_owner_can_acknowledge_resolve_and_reopen_with_audit_logs(): void
    {
        [$user, $server] = $this->infrastructure();
        $incident = $this->incident($server);

        $this->actingAs($user)->patch(route('security.incidents.status', $incident), ['status' => 'acknowledged'])->assertSessionHas('status');
        $this->assertSame('acknowledged', $incident->fresh()->status);
        $this->assertSame($user->id, $incident->fresh()->acknowledged_by);

        $this->actingAs($user)->patch(route('security.incidents.status', $incident), ['status' => 'resolved'])->assertSessionHas('status');
        $this->assertSame('resolved', $incident->fresh()->status);

        $this->actingAs($user)->patch(route('security.incidents.status', $incident), ['status' => 'open'])->assertSessionHas('status');
        $this->assertSame('open', $incident->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_explicit_public_ip_block_creates_firewall_rule_and_private_ip_is_rejected(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        $incident = $this->incident($server);

        $this->actingAs($user)
            ->post(route('security.incidents.block', $incident), ['confirm' => '1'])
            ->assertSessionHas('status');

        $rule = FirewallRule::sole();
        $this->assertSame('deny', $rule->type);
        $this->assertSame('any', $rule->protocol);
        $this->assertSame('8.8.8.8', $rule->from_ip);
        $this->assertSame($rule->id, $incident->fresh()->firewall_rule_id);
        Queue::assertPushedOn('operations', SyncFirewallRuleJob::class);

        $private = $this->incident($server, 'Private source', '10.0.0.2');
        $this->actingAs($user)
            ->post(route('security.incidents.block', $private), ['confirm' => '1'])
            ->assertSessionHasErrors('mitigation');
        $this->assertSame(1, FirewallRule::count());
    }

    public function test_manual_scan_is_queued_and_notification_event_is_available(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();

        $this->actingAs($user)
            ->post(route('security.scan'), ['server_id' => $server->id])
            ->assertSessionHas('status');

        Queue::assertPushedOn('monitoring', CollectServerSecuritySignalsJob::class);
        $this->assertSame('queued', $server->fresh()->security_scan_status);
        $this->assertNull($server->fresh()->security_scan_message);
        $this->assertSame('Security incident', NotificationChannel::EVENTS['security_incident']);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $sidebar = str($html)->after('<aside')->before('</aside>')->toString();
        $this->assertStringContainsString(route('security.index'), $sidebar);
    }

    public function test_team_workspace_still_lists_personal_accessible_servers(): void
    {
        [$user, $server] = $this->infrastructure('uplaryproduction');
        Site::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'domain' => 'uplaryproduction.example.com',
            'php_version' => '8.4',
            'repository_url' => 'https://github.com/acme/app.git',
            'branch' => 'main',
            'status' => 'active',
            'webhook_secret' => str_repeat('a', 64),
        ]);
        $team = Team::create(['owner_id' => $user->id, 'name' => 'Developer', 'slug' => 'developer']);
        $team->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'accepted_at' => now()]);
        $user->update(['current_team_id' => $team->id]);

        $settings = app(SecurityDetectionSettings::class);
        $settings->saveFor($user, true, []);
        $this->assertSame('Developer', $settings->forUser($user->fresh())['scope']['label']);
        $this->assertNull($server->fresh()->team_id);

        $html = $this->actingAs($user->fresh())->get('/security')->assertOk()->getContent();
        $this->assertStringContainsString($server->name, $html);
        $this->assertStringContainsString('Developer', $html);
        $this->assertStringNotContainsString('Add a server to start protecting it', $html);
        $this->assertMatchesRegularExpression('/Protected servers<\/p>\s*<p[^>]*>\s*1\s*<\/p>/s', $html);
        $this->assertMatchesRegularExpression('/Protected sites<\/p>\s*<p[^>]*>\s*1\s*<\/p>/s', $html);
        $this->assertSame(1, $settings->serverQueryFor($user->fresh())->count());
        $this->assertTrue($settings->enabledForServer($server->fresh()));
    }

    public function test_scan_status_endpoint_is_tenant_scoped_and_requires_auth(): void
    {
        [$owner, $server] = $this->infrastructure('owner-status');
        [$other, $otherServer] = $this->infrastructure('other-status');
        $server->update(['security_scan_status' => 'running', 'security_scanned_at' => now()->subMinute()]);

        $this->getJson(route('security.status'))->assertUnauthorized();

        $payload = $this->actingAs($owner)->getJson(route('security.status'))->assertOk()->json();
        $ids = collect($payload['servers'])->pluck('id');
        $this->assertTrue($ids->contains($server->id));
        $this->assertFalse($ids->contains($otherServer->id));
        $this->assertSame('running', collect($payload['servers'])->firstWhere('id', $server->id)['status']);
        $this->assertSame('Scanning…', collect($payload['servers'])->firstWhere('id', $server->id)['label']);
        $this->assertArrayHasKey('last_scan_human', $payload['summary']);
    }

    public function test_security_page_shows_scanning_badge_when_status_running(): void
    {
        [$user, $server] = $this->infrastructure('scanning-badge');
        $server->update([
            'security_scan_status' => 'running',
            'security_scan_message' => null,
        ]);

        $this->actingAs($user)
            ->get('/security')
            ->assertOk()
            ->assertSee('Scanning…', false)
            ->assertSee('disabled', false);
    }

    public function test_successful_scan_updates_last_scan_timestamp(): void
    {
        [$user, $server] = $this->infrastructure('scan-stamp');
        $this->assertNull($server->security_scanned_at);
        $server->update(['security_scan_status' => 'queued']);

        Process::fake(function (object $process) {
            $command = $process->command;
            if (is_array($command) && ($command[0] ?? null) === 'whoami') {
                return Process::result(output: 'test-user', exitCode: 0);
            }
            if (is_array($command) && ($command[0] ?? null) === 'icacls') {
                return Process::result(output: '', exitCode: 0);
            }

            return Process::result(output: '', exitCode: 0);
        });

        (new CollectServerSecuritySignalsJob($server->id))->handle(
            app(SshClient::class),
            app(SecurityDetectorEngine::class),
            app(SecurityDetectionSettings::class),
        );

        $server->refresh();
        $this->assertNotNull($server->security_scanned_at);
        $this->assertSame('succeeded', $server->security_scan_status);
        $this->assertNull($server->security_scan_message);

        $html = $this->actingAs($user)->get('/security')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/Last completed scan<\/p>\s*<p[^>]*>\s*Never\s*<\/p>/s', $html);
    }

    public function test_failed_scan_sets_failed_status_with_safe_message(): void
    {
        [$user, $server] = $this->infrastructure('scan-fail');
        $server->update(['security_scan_status' => 'queued']);

        Process::fake(function (object $process) {
            $command = $process->command;
            if (is_array($command) && ($command[0] ?? null) === 'whoami') {
                return Process::result(output: 'test-user', exitCode: 0);
            }
            if (is_array($command) && ($command[0] ?? null) === 'icacls') {
                return Process::result(output: '', exitCode: 0);
            }

            return Process::result(
                errorOutput: 'SSH failed password=hunter2 for /Users/deploy/.ssh/id_rsa',
                exitCode: 1,
            );
        });

        try {
            (new CollectServerSecuritySignalsJob($server->id))->handle(
                app(SshClient::class),
                app(SecurityDetectorEngine::class),
                app(SecurityDetectionSettings::class),
            );
            $this->fail('Expected scan exception.');
        } catch (\RuntimeException) {
            // expected
        }

        $server->refresh();
        $this->assertSame('failed', $server->security_scan_status);
        $this->assertNotNull($server->security_scan_message);
        $this->assertStringNotContainsString('hunter2', $server->security_scan_message);
        $this->assertStringNotContainsString('/Users/deploy', $server->security_scan_message);

        $this->actingAs($user)
            ->get('/security')
            ->assertOk()
            ->assertSee('Failed', false);
    }

    public function test_empty_inventory_prompts_to_add_a_server(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/security')
            ->assertOk()
            ->assertSee('Add a server to start protecting it')
            ->assertSee('Never');
    }

    public function test_detection_is_enabled_by_default(): void
    {
        [$user, $server] = $this->infrastructure();
        $settings = app(SecurityDetectionSettings::class);

        $this->assertTrue($settings->forUser($user)['enabled']);
        $this->assertTrue($settings->enabledForServer($server));
        $this->actingAs($user)->get('/security')->assertOk()->assertSee('Default: on');
    }

    public function test_owner_can_save_validated_settings_with_an_audit_log(): void
    {
        [$user] = $this->infrastructure();
        $payload = $this->settingsPayload();
        $sshRule = collect($payload['rules'])->search(fn (array $rule) => $rule['key'] === 'ssh.failed_logins');
        $payload['rules'][$sshRule]['threshold'] = 8;
        $payload['rules'][$sshRule]['lookback_minutes'] = 12;
        $payload['rules'][$sshRule]['severity'] = 'critical';

        $this->actingAs($user)
            ->put(route('security.settings.update'), $payload)
            ->assertSessionHas('status');

        $resolved = app(SecurityDetectionSettings::class)->forUser($user);
        $this->assertSame(8, $resolved['rules']['ssh.failed_logins']['threshold']);
        $this->assertSame(12, $resolved['rules']['ssh.failed_logins']['lookback_minutes']);
        $this->assertSame('critical', $resolved['rules']['ssh.failed_logins']['severity']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security_detection.settings_updated']);
    }

    public function test_global_disable_prevents_manual_and_scheduled_scans(): void
    {
        Queue::fake();
        [$user, $server] = $this->infrastructure();
        app(SecurityDetectionSettings::class)->saveFor($user, false, []);

        $this->actingAs($user)
            ->post(route('security.scan'))
            ->assertSessionHasErrors('scan');

        (new DispatchSecurityScansJob)->handle(app(SecurityDetectionSettings::class));

        Queue::assertNotPushed(CollectServerSecuritySignalsJob::class);
        $this->assertTrue(app(SecurityDetectorEngine::class)->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'count' => 100,
        ]])->isEmpty());
        $this->assertDatabaseCount('security_incidents', 0);
    }

    public function test_disabled_rule_suppresses_incidents(): void
    {
        Notification::fake();
        [$user, $server] = $this->infrastructure();
        app(SecurityDetectionSettings::class)->saveFor($user, true, [
            'ssh.failed_logins' => ['enabled' => false],
        ]);

        $incidents = app(SecurityDetectorEngine::class)->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'source_ip' => '8.8.8.8',
            'count' => 100,
        ]]);

        $this->assertTrue($incidents->isEmpty());
        $this->assertDatabaseCount('security_incidents', 0);
    }

    public function test_custom_threshold_window_and_severity_take_effect(): void
    {
        Notification::fake();
        [$user, $server] = $this->infrastructure();
        $settings = app(SecurityDetectionSettings::class);
        $settings->saveFor($user, true, [
            'ssh.failed_logins' => [
                'enabled' => true,
                'threshold' => 7,
                'lookback_minutes' => 15,
                'severity' => 'critical',
            ],
        ]);

        $this->assertTrue(app(SecurityDetectorEngine::class)->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'source_ip' => '8.8.8.8',
            'count' => 6,
        ]])->isEmpty());

        $incident = app(SecurityDetectorEngine::class)->evaluate($server, [[
            'detector_key' => 'ssh.failed_logins',
            'source_ip' => '8.8.8.8',
            'count' => 7,
        ]])->sole();

        $this->assertSame('critical', $incident->severity);
        $this->assertSame(15, $settings->maxLookbackForServer($server));
    }

    public function test_settings_are_isolated_between_tenants(): void
    {
        [$firstUser, $firstServer] = $this->infrastructure('first');
        [, $secondServer] = $this->infrastructure('second');
        $team = Team::create(['owner_id' => $firstUser->id, 'name' => 'First tenant', 'slug' => 'first-tenant']);
        $team->memberships()->create(['user_id' => $firstUser->id, 'role' => 'owner', 'accepted_at' => now()]);
        $firstUser->update(['current_team_id' => $team->id]);
        $firstServer->update(['team_id' => $team->id]);

        app(SecurityDetectionSettings::class)->saveFor($firstUser, false, [
            'ssh.failed_logins' => ['enabled' => false],
        ]);

        $settings = app(SecurityDetectionSettings::class);
        $this->assertFalse($settings->enabledForServer($firstServer->fresh()));
        $this->assertTrue($settings->enabledForServer($secondServer));
        $this->assertTrue($settings->forServer($secondServer)['rules']['ssh.failed_logins']['enabled']);
        $this->assertDatabaseHas('security_detection_settings', ['team_id' => $team->id, 'user_id' => null]);
    }

    public function test_team_viewer_cannot_change_detection_settings(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::create(['owner_id' => $owner->id, 'name' => 'Security team', 'slug' => 'security-team']);
        $team->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'accepted_at' => now()]);
        $team->memberships()->create(['user_id' => $viewer->id, 'role' => 'viewer', 'accepted_at' => now()]);
        $viewer->update(['current_team_id' => $team->id]);

        $this->actingAs($viewer)
            ->put(route('security.settings.update'), [])
            ->assertForbidden();

        $this->assertDatabaseCount('security_detection_settings', 0);
    }

    public function test_reset_restores_recommended_defaults(): void
    {
        [$user] = $this->infrastructure();
        $settings = app(SecurityDetectionSettings::class);
        $settings->saveFor($user, false, [
            'ssh.failed_logins' => ['enabled' => false, 'threshold' => 99],
        ]);

        $this->actingAs($user)
            ->delete(route('security.settings.reset'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('security_detection_settings', 0);
        $this->assertTrue($settings->forUser($user)['enabled']);
        $this->assertSame(5, $settings->forUser($user)['rules']['ssh.failed_logins']['threshold']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security_detection.settings_reset']);
    }

    public function test_signed_agent_events_are_accepted_redacted_and_replay_protected(): void
    {
        Notification::fake();
        [, $server] = $this->infrastructure();
        $server->update(['monitoring_enabled' => true, 'monitoring_secret' => 'agent-secret']);
        $body = json_encode(['events' => [[
            'type' => 'malware_signature',
            'source_ip' => '8.8.8.8',
            'summary' => 'Known web shell signature',
            'evidence' => ['path' => '/var/www/app/public/cache.php', 'env_content' => 'APP_KEY=secret'],
        ]]], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = str_repeat('a', 32);
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, 'agent-secret');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MONITORING_TIMESTAMP' => $timestamp,
            'HTTP_X_MONITORING_NONCE' => $nonce,
            'HTTP_X_MONITORING_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/monitoring/'.$server->id.'/security-events', [], [], [], $headers, $body)
            ->assertAccepted()
            ->assertJsonPath('accepted', 1);

        $incident = SecurityIncident::sole();
        $this->assertSame('malware.signature', $incident->detector_key);
        $this->assertSame('[REDACTED]', $incident->evidence['env_content']);

        $this->call('POST', '/api/monitoring/'.$server->id.'/security-events', [], [], [], $headers, $body)
            ->assertStatus(409);
    }

    private function incident(Server $server, string $title = 'SSH attack detected', string $ip = '8.8.8.8'): SecurityIncident
    {
        return SecurityIncident::create([
            'user_id' => $server->user_id,
            'team_id' => $server->team_id,
            'server_id' => $server->id,
            'detector_key' => 'ssh.failed_logins',
            'rule_name' => 'Repeated failed SSH logins',
            'source' => 'auth',
            'severity' => 'critical',
            'status' => 'open',
            'source_ip' => $ip,
            'title' => $title,
            'summary' => 'Threshold reached.',
            'evidence' => ['attempts' => 5],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrence_count' => 5,
        ]);
    }

    private function infrastructure(string $suffix = 'app'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create([
            'user_id' => $user->id,
            'provider' => 'digitalocean',
            'name' => 'Production '.$suffix,
            'credentials' => ['token' => 'secret'],
            'validated_at' => now(),
        ]);
        $key = SshKey::create([
            'user_id' => $user->id,
            'name' => 'Managed '.$suffix,
            'public_key' => 'ssh-ed25519 AAAA '.$suffix,
            'private_key' => 'private-key',
        ]);
        $server = Server::create([
            'user_id' => $user->id,
            'cloud_account_id' => $account->id,
            'ssh_key_id' => $key->id,
            'name' => 'Server '.$suffix,
            'hostname' => $suffix.'-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
            'public_ip' => '8.8.4.4',
            'status' => ServerStatus::Ready,
        ]);

        return [$user, $server];
    }

    private function settingsPayload(): array
    {
        return [
            'enabled' => true,
            'rules' => collect(config('security-detection.rules'))->map(fn (array $rule, string $key) => [
                'key' => $key,
                'enabled' => $rule['enabled'],
                'threshold' => $rule['threshold'],
                'lookback_minutes' => $rule['lookback_minutes'],
                'severity' => $rule['severity'],
            ])->values()->all(),
        ];
    }
}
