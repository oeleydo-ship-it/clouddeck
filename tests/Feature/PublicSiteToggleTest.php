<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // An install with no accounts at all is sent to the first-run installer, which
        // would answer every one of these requests before the switch is even consulted.
        User::factory()->create(['email_verified_at' => now()]);
    }

    private function disablePublicSite(): void
    {
        app(SystemSettings::class)->put('public_site_enabled', '0', 'boolean');
    }

    public function test_the_marketing_pages_are_served_by_default(): void
    {
        $this->get('/')->assertOk()->assertSee('Get started');
        $this->get('/about')->assertOk();
        $this->get('/features')->assertOk();
        $this->get('/use-cases')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/blog')->assertOk();
    }

    public function test_turning_the_public_site_off_sends_a_visitor_to_sign_in(): void
    {
        $this->disablePublicSite();

        // Every one of them, not just the home page: an install on a subdomain should have
        // no public front at all.
        foreach (['/', '/about', '/features', '/use-cases', '/contact', '/blog'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_an_individual_blog_post_is_not_left_reachable_by_its_own_url(): void
    {
        $post = Post::create(['title' => 'Release notes', 'slug' => 'release-notes', 'body' => 'Shipped.', 'published_at' => now()]);
        $this->get("/blog/{$post->slug}")->assertOk();

        $this->disablePublicSite();

        $this->get("/blog/{$post->slug}")->assertRedirect(route('login'));
    }

    public function test_a_signed_in_visitor_goes_to_the_product_rather_than_the_login_form(): void
    {
        $this->disablePublicSite();
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Bouncing someone who is already authenticated to /login would only bounce them
        // straight back out again.
        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_signing_in_and_registering_still_work_with_the_public_site_off(): void
    {
        $this->disablePublicSite();

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    public function test_a_superadmin_can_turn_the_public_site_off_and_on(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'super_admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['platform_name' => 'Uplary'])->assertRedirect();
        $this->assertFalse(app(SystemSettings::class)->publicSiteEnabled());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['platform_name' => 'Uplary', 'public_site_enabled' => '1'])->assertRedirect();
        $this->assertTrue(app(SystemSettings::class)->publicSiteEnabled());
    }

    public function test_only_a_superadmin_can_change_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->put(route('admin.settings.update'), ['public_site_enabled' => '0'])->assertForbidden();
        $this->assertTrue(app(SystemSettings::class)->publicSiteEnabled());
    }
}
