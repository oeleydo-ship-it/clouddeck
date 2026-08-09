<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    public static function sections(): array
    {
        return [
            ['/admin', 'Overview'],
            ['/admin/users', 'Users'],
            ['/admin/plans', 'Plans'],
            ['/admin/managed-servers', 'Managed servers'],
            ['/admin/feature-flags', 'Feature flags'],
            ['/admin/billing-review', 'Billing review'],
            ['/admin/payments', 'Payments'],
            ['/admin/storage', 'Storage'],
            ['/admin/mail', 'SMTP'],
            ['/admin/notifications', 'Notification center'],
            ['/admin/pages', 'Pages'],
            ['/admin/seo', 'SEO'],
            ['/admin/analytics', 'Analytics'],
            ['/admin/webmaster', 'Webmaster'],
            ['/admin/insert-code', 'Insert code'],
            ['/admin/ai', 'AI'],
            ['/admin/google-auth', 'Google Auth'],
            ['/admin/settings', 'Settings'],
            ['/admin/audit', 'Audit'],
        ];
    }

    #[DataProvider('sections')]
    public function test_every_admin_section_renders_for_a_super_admin(string $path, string $title): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        Plan::create(['slug' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => 1], 'features' => [], 'active' => true, 'public' => true, 'sort_order' => 10]);
        AuditLog::create(['actor_id' => $admin->id, 'action' => 'settings.updated', 'ip_address' => '127.0.0.1']);

        $this->actingAs($admin)->get($path)->assertOk()->assertSee($title);
    }

    #[DataProvider('sections')]
    public function test_no_admin_section_is_reachable_by_a_customer(string $path): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($path)->assertForbidden();
    }

    public function test_the_sidebar_marks_the_section_being_viewed(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            // Every section stays reachable from wherever the operator happens to be.
            ->assertSee(route('admin.users'), false)
            ->assertSee(route('admin.managed-servers'), false)
            ->assertSee(route('admin.pages'), false)
            ->assertSee(route('admin.seo'), false)
            ->assertSee(route('admin.analytics'), false)
            ->assertSee(route('admin.webmaster'), false)
            ->assertSee(route('admin.insert-code'), false)
            ->assertSee(route('admin.ai'), false)
            ->assertSee(route('admin.google-auth'), false)
            ->assertSee(route('admin.mail'), false)
            ->assertSee(route('admin.notifications'), false)
            ->assertSee(route('admin.storage'), false)
            ->assertSee(route('admin.audit'), false);
    }

    public function test_user_search_survives_pagination_on_its_own_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

        $this->actingAs($admin)->get('/admin/users?search=findable')
            ->assertOk()
            ->assertSee('findable@example.com')
            ->assertDontSee('other@example.com');
    }
}
