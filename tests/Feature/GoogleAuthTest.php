<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_google_button_appears_when_configured(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('googleAuthEnabled', true)
            ->where('googleButtonLabel', 'Continue with Google')
            ->where('googleRedirect', route('auth.google.redirect')));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('googleAuthEnabled', true)
            ->where('googleButtonLabel', 'Continue with Google')
            ->where('googleRedirect', route('auth.google.redirect')));
    }

    public function test_google_button_is_hidden_when_not_configured(): void
    {
        config([
            'services.google.enabled' => true,
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('googleAuthEnabled', false)
            ->where('googleButtonLabel', null));
    }

    public function test_google_button_is_hidden_when_admin_disables_it(): void
    {
        app(\App\Services\SystemSettings::class)->put('google_auth_enabled', '0', 'boolean', true);
        app(\App\Services\SystemSettings::class)->put('google_client_id', 'test-client-id', 'string', false);
        app(\App\Services\SystemSettings::class)->put('google_client_secret', 'test-client-secret', 'string', false);
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('googleAuthEnabled', false)
            ->where('googleButtonLabel', null));
    }

    public function test_superadmin_can_save_google_auth_settings_and_buttons_appear(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.enabled' => false,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/admin/google-auth')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/GoogleAuth')
                ->where('title', 'Google Auth')
                ->where('enableLabel', 'Enable Google sign-in')
                ->where('callbackUrl', url('/auth/google/callback')))
            ->assertDontSee('test-secret-value', false);

        $this->actingAs($admin)->put('/admin/settings/google-auth', [
            'google_auth_enabled' => '1',
            'google_client_id' => 'admin-client-id.apps.googleusercontent.com',
            'google_client_secret' => 'GOCSPX-admin-secret',
        ])->assertSessionHas('status');

        $settings = app(\App\Services\SystemSettings::class);
        $this->assertTrue($settings->googleAuthEnabled());
        $this->assertSame('admin-client-id.apps.googleusercontent.com', $settings->googleClientId());
        $this->assertSame('GOCSPX-admin-secret', $settings->googleClientSecret());
        $this->assertFalse((bool) \App\Models\SystemSetting::whereKey('google_client_secret')->value('is_public'));

        // Blank secret keeps the stored value; page never echoes the secret.
        $this->actingAs($admin)->put('/admin/settings/google-auth', [
            'google_auth_enabled' => '1',
            'google_client_id' => 'admin-client-id.apps.googleusercontent.com',
            'google_client_secret' => '',
        ])->assertSessionHas('status');

        $this->assertSame('GOCSPX-admin-secret', $settings->googleClientSecret());

        $this->actingAs($admin)->get('/admin/google-auth')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/GoogleAuth')
                ->where('secretSaved', true)
                ->where('secretPlaceholder', 'Saved — leave blank to keep it'))
            ->assertDontSee('GOCSPX-admin-secret', false);

        $this->post('/logout');

        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('googleAuthEnabled', true)
            ->where('googleButtonLabel', 'Continue with Google'));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('googleAuthEnabled', true)
            ->where('googleButtonLabel', 'Continue with Google'));
    }

    public function test_customers_cannot_open_or_save_google_auth_settings(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get('/admin/google-auth')->assertForbidden();
        $this->actingAs($customer)->put('/admin/settings/google-auth', [
            'google_auth_enabled' => '1',
            'google_client_id' => 'evil',
            'google_client_secret' => 'evil',
        ])->assertForbidden();
    }

    public function test_boot_applies_google_settings_over_env(): void
    {
        app(\App\Services\SystemSettings::class)->put('google_auth_enabled', '1', 'boolean', true);
        app(\App\Services\SystemSettings::class)->put('google_client_id', 'from-settings-id', 'string', false);
        app(\App\Services\SystemSettings::class)->put('google_client_secret', 'from-settings-secret', 'string', false);

        config([
            'services.google.client_id' => 'from-env-id',
            'services.google.client_secret' => 'from-env-secret',
            'services.google.enabled' => false,
        ]);

        $this->app->make(\App\Providers\AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertSame('from-settings-id', config('services.google.client_id'));
        $this->assertSame('from-settings-secret', config('services.google.client_secret'));
        $this->assertTrue(config('services.google.enabled'));
    }

    public function test_callback_creates_a_customer_user_from_verified_google_account(): void
    {
        $this->fakeGoogleUser([
            'id' => 'google-100',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-100', $user->google_id);
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_logs_in_an_existing_google_user(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'google_id' => 'google-100',
            'email_verified_at' => now(),
        ]);

        $this->fakeGoogleUser([
            'id' => 'google-100',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('email', 'ada@example.com')->count());
    }

    public function test_callback_links_google_to_existing_password_account_without_changing_role(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('a-secure-password-99'),
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['role' => 'customer'])->save();

        $this->fakeGoogleUser([
            'id' => 'google-200',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertSame('google-200', $user->google_id);
        $this->assertSame('customer', $user->role);
        $this->assertTrue(Hash::check('a-secure-password-99', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_cannot_escalate_an_existing_super_admin_via_linking(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('a-secure-password-99'),
            'email_verified_at' => now(),
        ]);
        $admin->forceFill(['role' => 'super_admin'])->save();

        $this->fakeGoogleUser([
            'id' => 'google-admin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'email_verified' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $admin->refresh();
        $this->assertSame('google-admin', $admin->google_id);
        $this->assertSame('super_admin', $admin->role);
    }

    public function test_callback_rejects_unverified_google_emails(): void
    {
        $this->fakeGoogleUser([
            'id' => 'google-300',
            'name' => 'Unverified',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
    }

    public function test_oauth_never_creates_super_admin(): void
    {
        $this->fakeGoogleUser([
            'id' => 'google-400',
            'name' => 'New User',
            'email' => 'new@example.com',
            'email_verified' => true,
        ]);

        $this->get('/auth/google/callback');

        $this->assertSame('customer', User::where('email', 'new@example.com')->value('role'));
        $this->assertSame(0, User::where('role', 'super_admin')->count());
    }

    public function test_register_ignores_role_mass_assignment(): void
    {
        $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'SecurePass1234',
            'password_confirmation' => 'SecurePass1234',
            'role' => 'super_admin',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'attacker@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_throttle_still_applies(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('correct-password-12'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors();
        }

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong-password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_password_login_still_works_after_google_link(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('a-secure-password-99'),
            'google_id' => 'google-200',
            'email_verified_at' => now(),
        ]);

        Auth::logout();
        $this->post('/login', [
            'email' => 'ada@example.com',
            'password' => 'a-secure-password-99',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @param  array{id: string, name: string, email: string, email_verified: bool}  $attributes
     */
    private function fakeGoogleUser(array $attributes): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $attributes['id'],
            'nickname' => null,
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'avatar' => null,
        ]);
        $socialiteUser->user = [
            'email_verified' => $attributes['email_verified'],
        ];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
