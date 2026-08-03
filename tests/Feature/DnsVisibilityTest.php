<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DNS management is optional: plenty of installs keep DNS with whoever holds the
 * registrar, and there the section is a question the operator would rather not be asked.
 */
class DnsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dns_is_offered_by_default(): void
    {
        $this->assertStringContainsString(route('dns.index'), $this->navigation());
        $this->actingAs(User::factory()->create())->get('/dns')->assertOk();
    }

    public function test_turning_it_off_takes_the_entry_out_of_the_navigation(): void
    {
        app(SystemSettings::class)->put('dns_enabled', '0', 'boolean', true);

        $this->assertStringNotContainsString('/dns', $this->navigation());
    }

    private function navigation(): string
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

        return str($html)->after('<aside')->before('</aside>')->toString();
    }

    public function test_turning_it_off_closes_the_urls_too(): void
    {
        app(SystemSettings::class)->put('dns_enabled', '0', 'boolean', true);
        $user = User::factory()->create();

        // Hiding the nav entry alone would leave a kept link working, which is not a switch.
        $this->actingAs($user)->get('/dns')->assertNotFound();
        $this->actingAs($user)->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])->assertNotFound();
    }

    public function test_an_administrator_can_turn_it_back_on(): void
    {
        app(SystemSettings::class)->put('dns_enabled', '0', 'boolean', true);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->put('/admin/settings', [
            'platform_name' => 'CloudDeck',
            'dns_enabled' => '1',
            'registration_enabled' => '1',
            'public_site_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue(app(SystemSettings::class)->dnsEnabled());
        $this->actingAs($admin)->get('/dns')->assertOk();
    }

    public function test_the_sidebar_no_longer_pins_a_provision_button_over_every_page(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();
        $sidebar = str($html)->after('<aside')->before('</aside>')->toString();

        // The action lives on the Servers and Dashboard pages, where the servers are. It
        // stays a search destination, which is why this looks inside the nav rather than
        // counting the phrase across the whole page.
        $this->assertStringNotContainsString('Provision server', $sidebar);
        $this->assertStringContainsString('Provision server', $html);
    }
}
