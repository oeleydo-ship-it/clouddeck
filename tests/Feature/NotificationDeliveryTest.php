<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\OperationalEventNotification;
use App\Notifications\OutboundMessage;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function channel(User $user, string $type, array $configuration, ?array $events = null): NotificationChannel
    {
        return $user->notificationChannels()->create([
            'name' => ucfirst($type), 'type' => $type, 'configuration' => $configuration, 'events' => $events, 'enabled' => true,
        ]);
    }

    private function message(string $event = 'deploy_complete'): OutboundMessage
    {
        return new OutboundMessage($event, 'Deployment successful', 'app.example.com is live.', 'https://clouddeck.test/d/1');
    }

    public function test_each_destination_is_sent_the_shape_it_expects(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->send($this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X']), $this->message());
        $dispatcher->send($this->channel($user, 'discord', ['webhook_url' => 'https://discord.com/api/webhooks/1/x']), $this->message());
        $dispatcher->send($this->channel($user, 'telegram', ['bot_token' => 'bot-token', 'chat_id' => '4321']), $this->message());
        $dispatcher->send($this->channel($user, 'push', ['app_token' => 'app', 'user_key' => 'key']), $this->message());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && isset($r->data()['text']));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'discord.com') && isset($r->data()['content']));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org/botbot-token/sendMessage') && $r->data()['chat_id'] === '4321');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.pushover.net') && $r->data()['title'] === 'Deployment successful');
    }

    public function test_an_sms_carries_the_credentials_and_a_bounded_body(): void
    {
        Http::fake(['*' => Http::response(['sid' => 'SM1'])]);
        $user = User::factory()->create();
        $channel = $this->channel($user, 'sms', ['account_sid' => 'AC123', 'auth_token' => 'secret', 'from' => '+14155550123', 'to' => '+14155550124']);

        app(NotificationDispatcher::class)->send($channel, new OutboundMessage('deploy_complete', 'Deployed', str_repeat('x', 900)));

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('/Accounts/AC123/Messages.json', $request->url());
            // Charged by the segment, so this is the one destination that gets a short form.
            $this->assertLessThanOrEqual(300, mb_strlen($request->data()['Body']));

            return true;
        });
    }

    public function test_a_channel_only_receives_the_events_it_subscribed_to(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $channel = $this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'], ['ssl_expiring']);

        $this->assertFalse(app(NotificationDispatcher::class)->send($channel, $this->message('deploy_complete')));
        $this->assertTrue(app(NotificationDispatcher::class)->send($channel, $this->message('ssl_expiring')));

        Http::assertSentCount(1);
    }

    public function test_naming_no_events_means_all_of_them(): void
    {
        $user = User::factory()->create();
        $channel = $this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'], []);

        // A channel created before events existed carries none, and must not go quiet.
        foreach (array_keys(NotificationChannel::EVENTS) as $event) {
            $this->assertTrue($channel->wantsEvent($event));
        }
    }

    public function test_a_disabled_channel_is_skipped(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $channel = $this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X']);
        $channel->update(['enabled' => false]);

        $this->assertFalse(app(NotificationDispatcher::class)->send($channel, $this->message()));
        Http::assertNothingSent();
    }

    public function test_one_destination_failing_does_not_stop_the_others(): void
    {
        Http::fake([
            'hooks.slack.com/*' => Http::response('gone', 410),
            '*' => Http::response(['ok' => true]),
        ]);
        $user = User::factory()->create();
        $slack = $this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X']);
        $discord = $this->channel($user, 'discord', ['webhook_url' => 'https://discord.com/api/webhooks/1/x']);
        $dispatcher = app(NotificationDispatcher::class);

        // A revoked webhook is normal, and must never fail the alert that raised it.
        $this->assertFalse($dispatcher->send($slack, $this->message()));
        $this->assertTrue($dispatcher->send($discord, $this->message()));
    }

    public function test_a_destination_missing_its_configuration_is_refused_rather_than_called(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->assertFalse(app(NotificationDispatcher::class)->send($this->channel($user, 'sms', ['account_sid' => 'AC1']), $this->message()));
        $this->assertFalse(app(NotificationDispatcher::class)->send($this->channel($user, 'push', []), $this->message()));
        $this->assertFalse(app(NotificationDispatcher::class)->send($this->channel($user, 'telegram', ['bot_token' => 'x']), $this->message()));
        Http::assertNothingSent();
    }

    public function test_an_operational_event_reaches_the_configured_destinations(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $this->channel($user, 'slack', ['webhook_url' => 'https://hooks.slack.com/services/T/B/X']);

        $user->notify(new OperationalEventNotification('ssl_expiring', 'Certificate expiring', 'Two days left.', 'https://clouddeck.test'));

        Http::assertSent(fn ($r) => str_contains($r->data()['text'] ?? '', 'Certificate expiring'));
    }

    public function test_notifications_are_queued_away_from_the_work_that_raised_them(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $user->notify(new OperationalEventNotification('queue_failed', 'Failed jobs', 'Three jobs failed.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification) {
            // Delivery reaches third-party APIs; doing that inline would make a monitoring
            // run as slow as the slowest webhook.
            $this->assertSame('notifications', $notification->queue);

            return true;
        });
    }
}
