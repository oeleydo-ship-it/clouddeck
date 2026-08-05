<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeAndInsertCodeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_superadmin_can_save_stripe_credentials_and_config_resolves_them(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/payments')
            ->assertOk()
            ->assertSee('Stripe API', false)
            ->assertSee('/api/billing/stripe/webhook', false);

        $this->actingAs($admin)->put('/admin/settings/stripe', [
            'stripe_key' => 'pk_test_abc123',
            'stripe_secret' => 'sk_test_abc123',
            'stripe_webhook_secret' => 'whsec_test_abc123',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame('pk_test_abc123', $settings->stripeKey());
        $this->assertSame('sk_test_abc123', $settings->stripeSecret());
        $this->assertSame('whsec_test_abc123', $settings->stripeWebhookSecret());
        $this->assertFalse((bool) SystemSetting::whereKey('stripe_secret')->value('is_public'));

        // Blank fields keep stored secrets.
        $this->actingAs($admin)->put('/admin/settings/stripe', [
            'stripe_key' => '',
            'stripe_secret' => '',
            'stripe_webhook_secret' => '',
        ])->assertSessionHas('status');

        $this->assertSame('sk_test_abc123', SystemSetting::whereKey('stripe_secret')->value('value'));

        config([
            'services.stripe.key' => null,
            'services.stripe.secret' => null,
            'services.stripe.webhook_secret' => null,
        ]);

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertSame('pk_test_abc123', config('services.stripe.key'));
        $this->assertSame('sk_test_abc123', config('services.stripe.secret'));
        $this->assertSame('whsec_test_abc123', config('services.stripe.webhook_secret'));
    }

    public function test_customers_cannot_save_stripe_or_insert_code_settings(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->put('/admin/settings/stripe', [
            'stripe_secret' => 'sk_test_nope',
        ])->assertForbidden();

        $this->actingAs($customer)->put('/admin/settings/insert-code', [
            'insert_code_head' => '<script>window.__evil=1</script>',
            'insert_code_on_marketing' => '1',
        ])->assertForbidden();

        $this->actingAs($customer)->get('/admin/insert-code')->assertForbidden();
    }

    public function test_insert_code_is_saved_and_rendered_on_marketing_home(): void
    {
        $this->markInstalled();
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/insert-code', [
            'insert_code_head' => '<!-- insert-head-marker -->',
            'insert_code_body' => '<div id="insert-body-marker"></div>',
            'insert_code_on_marketing' => '1',
            'insert_code_on_console' => '0',
        ])->assertSessionHas('status');

        $insert = app(SystemSettings::class)->insertCode();
        $this->assertSame('<!-- insert-head-marker -->', $insert['head']);
        $this->assertSame('<div id="insert-body-marker"></div>', $insert['body']);
        $this->assertTrue($insert['on_marketing']);
        $this->assertFalse($insert['on_console']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<!-- insert-head-marker -->', false)
            ->assertSee('<div id="insert-body-marker"></div>', false);
    }

    public function test_insert_code_stays_off_console_when_console_toggle_disabled(): void
    {
        $this->markInstalled();
        $admin = $this->admin();

        app(SystemSettings::class)->put('insert_code_body', '<div id="insert-console-should-not-appear"></div>', 'string', false);
        app(SystemSettings::class)->put('insert_code_on_marketing', '1', 'boolean', true);
        app(SystemSettings::class)->put('insert_code_on_console', '0', 'boolean', true);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('insert-console-should-not-appear', false);
    }
}
