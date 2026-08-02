<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Site Owner',
            'email' => 'owner@example.com',
            'password' => 'correct-horse-99',
            'password_confirmation' => 'correct-horse-99',
            'support_email' => 'support@example.com',
            'registration_enabled' => '1',
            'email_verification_required' => '1',
            ...$overrides,
        ];
    }

    public function test_a_fresh_instance_sends_every_page_to_the_installer(): void
    {
        $this->get('/dashboard')->assertRedirect('/install');
        $this->get('/')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Install CloudDeck');
    }

    public function test_installing_creates_the_administrator_plans_and_settings(): void
    {
        $this->post('/install', $this->payload())->assertRedirect(route('admin.dashboard'));

        $admin = User::where('email', 'owner@example.com')->sole();
        $this->assertSame('super_admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('correct-horse-99', $admin->password));
        $this->assertAuthenticatedAs($admin);

        // A signup with no plan to land on cannot pass a quota check, so the installer has
        // to leave the catalogue populated and the administrator subscribed.
        $this->assertSame(3, Plan::count());
        $this->assertSame('active', $admin->subscriptions()->sole()->status);
        $this->assertSame('support@example.com', SystemSetting::whereKey('support_email')->value('value'));
        $this->assertNotNull(SystemSetting::whereKey('installed_at')->value('value'));
    }

    public function test_the_installer_closes_once_an_administrator_exists(): void
    {
        $this->post('/install', $this->payload())->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->get('/install')->assertRedirect(route('dashboard'));
        $this->post('/install', $this->payload(['email' => 'intruder@example.com']))->assertForbidden();

        $this->assertSame(1, User::where('role', 'super_admin')->count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_a_redeployment_never_reopens_the_installer(): void
    {
        // Deployments replace the release directory and re-run migrations but never touch
        // rows, so installed state has to survive purely on the presence of an administrator.
        $this->post('/install', $this->payload())->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');
        SystemSetting::whereKey('installed_at')->delete();

        $this->get('/install')->assertRedirect(route('dashboard'));
    }

    public function test_stripe_credentials_are_stored_privately_and_drive_the_billing_config(): void
    {
        $this->post('/install', $this->payload([
            'stripe_secret' => 'sk_test_51abcdef',
            'stripe_webhook_secret' => 'whsec_abcdef',
        ]))->assertRedirect(route('admin.dashboard'));

        $secret = SystemSetting::whereKey('stripe_secret')->sole();
        $this->assertSame('sk_test_51abcdef', $secret->value);
        $this->assertFalse($secret->is_public);
        $this->assertNotSame('sk_test_51abcdef', \DB::table('system_settings')->where('key', 'stripe_secret')->value('value'));

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();
        $this->assertSame('sk_test_51abcdef', config('services.stripe.secret'));
        $this->assertSame('whsec_abcdef', config('services.stripe.webhook_secret'));
    }

    public function test_the_installer_rejects_weak_passwords_and_malformed_stripe_keys(): void
    {
        $this->post('/install', $this->payload(['password' => 'short1', 'password_confirmation' => 'short1']))->assertSessionHasErrors('password');
        $this->post('/install', $this->payload(['stripe_secret' => 'pk_live_public_key']))->assertSessionHasErrors('stripe_secret');

        $this->assertSame(0, User::count());
    }

    public function test_webhooks_are_not_redirected_into_the_installer(): void
    {
        // A machine caller following a redirect would render HTML and record a 200, hiding
        // the fact that the delivery never reached the application. Reaching route-model
        // binding and 404ing on an unknown site is proof the exemption held: the installer
        // would have answered 302 before any binding ran.
        $this->postJson('/webhooks/sites/'.fake()->uuid())->assertNotFound();
    }

    public function test_api_clients_are_told_the_instance_is_not_installed_rather_than_redirected(): void
    {
        $this->getJson('/dashboard')->assertStatus(503)->assertJson(['message' => 'CloudDeck is not installed yet.']);
    }
}
