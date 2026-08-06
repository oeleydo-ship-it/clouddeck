<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlatformRuntime\Contracts\PlatformProcessLauncher;
use App\Services\PlatformRuntime\Contracts\PlatformSslProbe;
use App\Services\PlatformRuntime\FakePlatformProcessLauncher;
use App\Services\PlatformRuntime\FakePlatformSslProbe;
use App\Services\PlatformRuntime\PlatformPidStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function fakeLauncher(): FakePlatformProcessLauncher
    {
        $fake = new FakePlatformProcessLauncher;
        $this->app->instance(PlatformProcessLauncher::class, $fake);

        return $fake;
    }

    private function fakeSslProbe(): FakePlatformSslProbe
    {
        $fake = new FakePlatformSslProbe;
        $this->app->instance(PlatformSslProbe::class, $fake);

        return $fake;
    }

    public function test_super_admin_can_view_platform_services_page(): void
    {
        $this->fakeSslProbe();

        $this->actingAs($this->admin())
            ->get(route('admin.platform-services'))
            ->assertOk()
            ->assertSee('Platform services')
            ->assertSee('Control-plane runtime')
            ->assertSee('Redis')
            ->assertSee('Horizon')
            ->assertSee('Queue workers')
            ->assertSee('Reverb')
            ->assertSee('SSL / TLS')
            ->assertSee('Renew certificate');
    }

    public function test_super_admin_can_fetch_status_json(): void
    {
        $probe = $this->fakeSslProbe()->with([
            'days_remaining' => 45,
            'issuer' => "Let's Encrypt",
            'subject' => 'control.example.com',
        ]);

        config([
            'app.url' => 'https://control.example.com',
            'platform-services.ssl.treat_as_windows' => false,
            'platform-services.ssl.treat_as_local_host' => false,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.platform-services.status'))
            ->assertOk()
            ->assertJsonStructure([
                'platform',
                'windows',
                'pcntl',
                'horizon_recommended',
                'polled_at',
                'services' => [
                    'redis' => ['key', 'name', 'status', 'detail', 'actions'],
                    'horizon' => ['key', 'name', 'status', 'actions'],
                    'queue' => ['key', 'name', 'status', 'meta'],
                    'reverb' => ['key', 'name', 'status', 'meta'],
                ],
                'ssl' => [
                    'key',
                    'name',
                    'status',
                    'detail',
                    'domain',
                    'actions' => ['renew'],
                    'meta' => ['issuer', 'days_remaining'],
                ],
            ])
            ->assertJsonPath('ssl.status', 'valid')
            ->assertJsonPath('ssl.domain', 'control.example.com')
            ->assertJsonPath('ssl.meta.issuer', "Let's Encrypt")
            ->assertJsonPath('ssl.actions.renew', true);

        $this->assertNotEmpty($probe->calls);
        $this->assertSame('control.example.com', $probe->calls[0]['host']);
    }

    public function test_ssl_status_marks_http_app_url_as_not_https(): void
    {
        $this->fakeSslProbe();

        config(['app.url' => 'http://localhost:8000']);

        $this->actingAs($this->admin())
            ->getJson(route('admin.platform-services.status'))
            ->assertOk()
            ->assertJsonPath('ssl.status', 'not_https')
            ->assertJsonPath('ssl.actions.renew', false);
    }

    public function test_ssl_status_marks_expiring_soon_certificates(): void
    {
        $this->fakeSslProbe()->with([
            'days_remaining' => 12,
            'issuer' => "Let's Encrypt",
        ]);

        config([
            'app.url' => 'https://control.example.com',
            'platform-services.ssl.treat_as_local_host' => false,
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.platform-services.status'))
            ->assertOk()
            ->assertJsonPath('ssl.status', 'expiring_soon')
            ->assertJsonPath('ssl.meta.days_remaining', 12);
    }

    public function test_ssl_renew_runs_script_via_fake_launcher(): void
    {
        $this->fakeSslProbe()->with(['days_remaining' => 40]);
        $launcher = $this->fakeLauncher();
        // status() may probe certbot via `command -v`; renew then runs bash.
        $launcher->enqueueRunResult(['exit_code' => 0, 'output' => '/usr/bin/certbot', 'error' => '']);
        $launcher->enqueueRunResult(['exit_code' => 0, 'output' => 'renewed', 'error' => '']);

        config([
            'app.url' => 'https://control.example.com',
            'platform-services.ssl.treat_as_windows' => false,
            'platform-services.ssl.treat_as_local_host' => false,
            'platform-services.ssl.email' => 'ops@example.com',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.platform-services.ssl.renew'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotEmpty($launcher->ran);
        $bash = collect($launcher->ran)->first(fn (array $cmd) => ($cmd[0] ?? null) === 'bash' && str_contains((string) ($cmd[1] ?? ''), 'renew-platform-ssl'));
        $this->assertNotNull($bash);
    }

    public function test_ssl_renew_rejected_on_windows_override(): void
    {
        $this->fakeSslProbe();
        $launcher = $this->fakeLauncher();

        config([
            'app.url' => 'https://control.example.com',
            'platform-services.ssl.treat_as_windows' => true,
            'platform-services.ssl.treat_as_local_host' => false,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.platform-services.ssl.renew'))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame([], $launcher->ran);
    }

    public function test_non_admin_cannot_access_platform_services(): void
    {
        $this->fakeSslProbe();

        $this->actingAs($this->customer())
            ->get(route('admin.platform-services'))
            ->assertForbidden();

        $this->actingAs($this->customer())
            ->getJson(route('admin.platform-services.status'))
            ->assertForbidden();

        $this->actingAs($this->customer())
            ->postJson(route('admin.platform-services.start', 'queue'))
            ->assertForbidden();

        $this->actingAs($this->customer())
            ->postJson(route('admin.platform-services.ssl.renew'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_platform_services(): void
    {
        $this->get(route('admin.platform-services'))->assertRedirect();
    }

    public function test_starting_queue_worker_uses_fake_launcher_in_tests(): void
    {
        $this->fakeSslProbe();
        $fake = $this->fakeLauncher();
        $pids = $this->app->make(PlatformPidStore::class);

        $this->actingAs($this->admin())
            ->postJson(route('admin.platform-services.start', 'queue'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotEmpty($fake->started);
        $this->assertSame('queue', $fake->started[0]['service']);
        $this->assertSame('queue:work', $fake->started[0]['arguments'][0]);
        $this->assertNotNull($pids->read('queue'));
        $this->assertTrue($fake->isRunning((int) $pids->read('queue')));

        $this->actingAs($this->admin())
            ->postJson(route('admin.platform-services.stop', 'queue'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull($pids->read('queue'));
    }

    public function test_unknown_service_returns_not_found(): void
    {
        $this->fakeSslProbe();

        $this->actingAs($this->admin())
            ->postJson(route('admin.platform-services.start', 'mysql'))
            ->assertNotFound();
    }

    public function test_sidebar_includes_platform_services_link(): void
    {
        $this->fakeSslProbe();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.platform-services'), false)
            ->assertSee('Platform services');
    }
}
