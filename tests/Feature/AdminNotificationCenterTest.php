<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BillingPaymentFailedNotification;
use App\Notifications\OperationalEventNotification;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    public function test_superadmin_can_open_and_save_notification_center(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/notifications')
            ->assertOk()
            ->assertSee('Notification center', false)
            ->assertSee('Send operational alert emails', false)
            ->assertSee('Backup failed', false);

        $this->actingAs($admin)->put('/admin/settings/notifications', [
            'client_email_notifications_enabled' => '1',
            'events' => ['server_down', 'backup_failed'],
            'client_email_billing_payment_failed' => '1',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertTrue($settings->clientEmailNotificationsEnabled());
        $this->assertTrue($settings->clientEmailEventAllowed('server_down'));
        $this->assertTrue($settings->clientEmailEventAllowed('backup_failed'));
        $this->assertFalse($settings->clientEmailEventAllowed('deploy_complete'));
        $this->assertTrue($settings->clientEmailBillingFailedAllowed());
    }

    public function test_master_switch_blocks_all_operational_client_emails(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->put('/admin/settings/notifications', [
            'client_email_notifications_enabled' => '0',
            'events' => ['server_down'],
            'client_email_billing_payment_failed' => '1',
        ])->assertSessionHas('status');

        $user->notify(new OperationalEventNotification('server_down', 'Server offline', 'No heartbeat.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification, $channels) use ($user) {
            $this->assertSame(['database'], $channels);
            $this->assertNotContains('mail', $notification->via($user));

            return true;
        });
    }

    public function test_disabled_event_stays_in_bell_only(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->put('/admin/settings/notifications', [
            'client_email_notifications_enabled' => '1',
            'events' => ['server_down'],
            'client_email_billing_payment_failed' => '1',
        ])->assertSessionHas('status');

        $user->notify(new OperationalEventNotification('deploy_complete', 'Deploy done', 'Release live.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification, $channels) {
            $this->assertSame(['database'], $channels);

            return true;
        });

        $user->notify(new OperationalEventNotification('server_down', 'Server offline', 'No heartbeat.'));

        Notification::assertSentTo($user, OperationalEventNotification::class, function ($notification, $channels) {
            return $notification->event === 'server_down'
                && in_array('mail', $channels, true)
                && in_array('database', $channels, true);
        });
    }

    public function test_billing_failed_email_can_be_muted_separately(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->put('/admin/settings/notifications', [
            'client_email_notifications_enabled' => '1',
            'events' => array_keys(\App\Models\NotificationChannel::EVENTS),
            'client_email_billing_payment_failed' => '0',
        ])->assertSessionHas('status');

        $user->notify(new BillingPaymentFailedNotification('inv_test'));

        Notification::assertSentTo($user, BillingPaymentFailedNotification::class, function ($notification, $channels) {
            $this->assertSame(['database'], $channels);

            return true;
        });
    }

    public function test_customers_cannot_change_notification_center(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/admin/notifications')->assertForbidden();
        $this->actingAs($user)->put('/admin/settings/notifications', [
            'client_email_notifications_enabled' => '0',
        ])->assertForbidden();
    }
}
