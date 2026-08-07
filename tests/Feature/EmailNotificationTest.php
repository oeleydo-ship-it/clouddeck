<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function recipient(User $user, ?string $address = null, ?array $events = null): NotificationChannel
    {
        return $user->notificationChannels()->create([
            'name' => 'Ops', 'type' => 'email',
            'configuration' => $address ? ['address' => $address] : [],
            'events' => $events, 'enabled' => true,
        ]);
    }

    public function test_with_no_recipients_configured_everything_goes_to_the_account_address(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        // Silence must never be what happens by default: someone who has never opened the
        // settings page still hears about their servers.
        foreach (array_keys(NotificationChannel::EVENTS) as $event) {
            $this->assertSame(['owner@example.com'], $user->emailRecipientsFor($event));
        }
    }

    public function test_a_recipient_can_send_alerts_to_a_shared_mailbox(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->recipient($user, 'ops@example.com');

        $this->assertSame(['ops@example.com'], $user->emailRecipientsFor('deploy_complete'));
    }

    public function test_a_recipient_without_an_address_falls_back_to_the_account(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->recipient($user);

        $this->assertSame(['owner@example.com'], $user->emailRecipientsFor('site_added'));
    }

    public function test_each_recipient_chooses_which_events_reach_it(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->recipient($user, 'ops@example.com', ['server_down', 'disk_full']);
        $this->recipient($user, 'devs@example.com', ['deploy_complete']);

        $this->assertSame(['ops@example.com'], $user->emailRecipientsFor('server_down'));
        $this->assertSame(['devs@example.com'], $user->emailRecipientsFor('deploy_complete'));
        // Nobody asked for this one, so nobody is told about it.
        $this->assertSame([], $user->emailRecipientsFor('ssl_expiring'));
    }

    public function test_one_address_listed_twice_is_mailed_once(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->recipient($user, 'ops@example.com', ['server_down']);
        $this->recipient($user, 'ops@example.com', ['server_down', 'disk_full']);

        $this->assertSame(['ops@example.com'], $user->emailRecipientsFor('server_down'));
    }

    public function test_a_disabled_recipient_is_skipped(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->recipient($user, 'ops@example.com')->update(['enabled' => false]);

        // The last enabled recipient going away means nobody asked for it, not that the
        // account address quietly starts receiving everything again.
        $this->assertSame([], $user->emailRecipientsFor('server_down'));
    }

    public function test_an_unsubscribed_event_is_still_recorded_but_not_mailed(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->recipient($user, 'ops@example.com', ['server_down']);

        $user->notify(new OperationalEventNotification('ssl_expiring', 'Certificate expiring', 'Two days left.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification, $channels) use ($user) {
            // The record of what happened is not the same thing as being told about it.
            $this->assertSame(['database'], $channels);
            $this->assertNotContains('mail', $notification->via($user));

            return true;
        });
    }

    public function test_a_subscribed_event_is_mailed_and_recorded(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->recipient($user, 'ops@example.com', ['server_down']);

        $user->notify(new OperationalEventNotification('server_down', 'Server offline', 'No heartbeat for 5 minutes.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification, $channels) {
            $this->assertContains('mail', $channels);
            $this->assertContains('database', $channels);

            return true;
        });
    }

    public function test_the_recipient_form_accepts_an_address_and_events(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('notification-channels.store'), [
            'name' => 'Operations', 'address' => 'ops@example.com', 'events' => ['server_down', 'ssl_expiring'],
        ])->assertRedirect(route('notifications.index', ['tab' => 'email']))->assertSessionHas('status');

        $channel = NotificationChannel::sole();
        $this->assertSame('email', $channel->type);
        $this->assertSame('ops@example.com', $channel->configuration['address']);
        $this->assertSame(['server_down', 'ssl_expiring'], $channel->events);
    }

    public function test_an_event_that_does_not_exist_is_refused(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('notification-channels.store'), [
            'name' => 'Operations', 'events' => ['everything'],
        ])->assertSessionHasErrors('events.0');

        $this->actingAs($user)->post(route('notification-channels.store'), [
            'name' => 'Operations', 'address' => 'not-an-address',
        ])->assertSessionHasErrors('address');
    }

    public function test_a_stranger_cannot_remove_someone_elses_recipient(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $channel = $this->recipient($user, 'ops@example.com');
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->delete(route('notification-channels.destroy', $channel))->assertNotFound();
        $this->assertDatabaseHas('notification_channels', ['id' => $channel->id, 'deleted_at' => null]);
    }
}
