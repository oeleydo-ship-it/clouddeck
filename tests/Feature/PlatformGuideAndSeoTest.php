<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformGuideAndSeoTest extends TestCase
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

    public function test_superadmin_can_save_landing_seo_and_ai_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/landing', [
            'landing_hero_headline' => 'Ship with confidence.',
            'landing_hero_subcopy' => 'Managed ops for your own VPS.',
            'landing_hero_cta_primary' => 'Start now',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->put('/admin/settings/seo', [
            'seo_default_description' => 'Custom meta for search.',
            'seo_default_title' => 'Custom platform title',
            'seo_title_template' => '{page} | {site}',
            'seo_robots' => 'index,follow',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->put('/admin/settings/analytics', [
            'ga_measurement_id' => 'G-TEST12345',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->put('/admin/settings/webmaster', [
            'gsc_verification' => 'abc_verify_token',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->put('/admin/settings/ai', [
            'ai_guide_enabled' => '1',
            'openai_api_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertSame('Ship with confidence.', $settings->landing()['hero_headline']);
        $this->assertSame('Custom meta for search.', $settings->seo()['description']);
        $this->assertSame('Custom platform title', $settings->seo()['title']);
        $this->assertSame('{page} | {site}', $settings->seo()['title_template']);
        $this->assertSame('G-TEST12345', $settings->analytics()['ga_measurement_id']);
        $this->assertSame('abc_verify_token', $settings->analytics()['gsc_verification']);
        $this->assertTrue($settings->aiGuideEnabled());
        $this->assertSame('sk-test-key', SystemSetting::whereKey('openai_api_key')->value('value'));
    }

    public function test_homepage_renders_edited_landing_copy_and_seo_tags(): void
    {
        $this->markInstalled();
        app(SystemSettings::class)->put('landing_hero_headline', 'Custom headline for SEO test', 'string', true);
        app(SystemSettings::class)->put('seo_default_description', 'Search description here', 'string', true);
        app(SystemSettings::class)->put('gsc_verification', 'gsc-token-xyz', 'string', true);
        app(SystemSettings::class)->put('ga_measurement_id', 'G-ABCDEFG', 'string', true);

        $this->get('/')
            ->assertOk()
            ->assertSee('Custom headline for SEO test')
            ->assertSee('Search description here', false)
            ->assertSee('google-site-verification', false)
            ->assertSee('gsc-token-xyz', false)
            ->assertSee('G-ABCDEFG', false);
    }

    public function test_ai_guide_chat_requires_enablement_and_returns_model_reply(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->postJson('/guide/chat', ['message' => 'How do I add a server?'])->assertNotFound();

        app(SystemSettings::class)->put('ai_guide_enabled', '1', 'boolean', true);
        app(SystemSettings::class)->put('openai_api_key', 'sk-live', 'string', false);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Open Providers, then connect Contabo by IP.']]],
            ], 200),
        ]);

        $this->actingAs($user)->postJson('/guide/chat', ['message' => 'How do I add Contabo?'])
            ->assertOk()
            ->assertJson(['reply' => 'Open Providers, then connect Contabo by IP.']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-live')
            && str_contains($request['messages'][0]['content'] ?? '', 'guide'));
    }

    public function test_customers_cannot_change_admin_landing_settings(): void
    {
        $this->actingAs($this->customer())
            ->put('/admin/settings/landing', ['landing_hero_headline' => 'Nope'])
            ->assertForbidden();
    }
}
