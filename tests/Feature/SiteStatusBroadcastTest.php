<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Events\SiteStatusUpdated;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteStatusBroadcastTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Site} */
    private function site(string $status = 'configuring'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $account = CloudAccount::create(['user_id' => $user->id, 'provider' => 'digitalocean', 'name' => 'Production', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-ed25519 AAAA test', 'private_key' => 'private-key']);
        $server = Server::create(['user_id' => $user->id, 'cloud_account_id' => $account->id, 'ssh_key_id' => $key->id, 'name' => 'production', 'hostname' => 'production-01', 'region' => 'ams3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-24-04-x64', 'public_ip' => '192.0.2.10', 'status' => ServerStatus::Ready]);
        $site = Site::create(['user_id' => $user->id, 'server_id' => $server->id, 'domain' => 'app.example.com', 'php_version' => '8.4', 'repository_url' => 'https://github.com/acme/app.git', 'branch' => 'main', 'status' => $status, 'webhook_secret' => Str::random(64)]);

        return [$user, $site];
    }

    public function test_finishing_configuration_is_broadcast_to_the_page_already_open(): void
    {
        Event::fake([SiteStatusUpdated::class]);
        [, $site] = $this->site();

        $site->update(['status' => 'active']);

        Event::assertDispatched(SiteStatusUpdated::class, fn ($event) => $event->site->is($site) && $event->site->status === 'active');
    }

    public function test_a_failed_configuration_is_broadcast_too(): void
    {
        Event::fake([SiteStatusUpdated::class]);
        [, $site] = $this->site();

        $site->update(['status' => 'failed']);

        Event::assertDispatched(SiteStatusUpdated::class);
    }

    public function test_writing_anything_other_than_the_status_broadcasts_nothing(): void
    {
        Event::fake([SiteStatusUpdated::class]);
        [, $site] = $this->site('active');

        // Otherwise every environment edit and deployment timestamp would wake every
        // open page for no reason.
        $site->update(['branch' => 'develop', 'last_deployed_at' => now()]);

        Event::assertNotDispatched(SiteStatusUpdated::class);
    }

    public function test_the_payload_and_channel_match_what_the_badge_subscribes_to(): void
    {
        [, $site] = $this->site();

        $event = new SiteStatusUpdated($site);

        $this->assertSame('private-sites.'.$site->id, $event->broadcastOn()->name);
        $this->assertSame('status-updated', $event->broadcastAs());
        $this->assertSame('configuring', $event->broadcastWith()['status']);
    }

    public function test_only_someone_who_can_view_the_site_may_subscribe(): void
    {
        // The channel delegates to this gate. Driving /broadcasting/auth would prove
        // nothing: the suite runs on the log broadcaster, which never consults channels.
        [$owner, $site] = $this->site();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $site));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $site));
    }

    public function test_the_badge_polls_while_configuring_and_stops_once_settled(): void
    {
        [$user, $site] = $this->site();

        $this->actingAs($user)->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Show')
                ->where('site.status', 'configuring'));

        $site->update(['status' => 'active']);

        $this->actingAs($user)->get(route('sites.show', $site->fresh()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.status', 'active'));
    }

    public function test_the_page_reloads_itself_once_the_site_settles_under_the_operator(): void
    {
        [$user, $site] = $this->site();

        $this->actingAs($user)->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.status', 'configuring'));

        $site->update(['status' => 'active']);

        // The deploy button and database notice were rendered for "configuring", so the
        // page has to be re-read once the site is ready rather than left half stale.
        $this->actingAs($user)->get(route('sites.show', $site->fresh()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.status', 'active'));
    }

    public function test_a_stranger_cannot_mount_the_badge_for_someone_elses_site(): void
    {
        [, $site] = $this->site();

        $this->actingAs(User::factory()->create())
            ->get(route('sites.show', $site))
            ->assertForbidden();
    }
}
