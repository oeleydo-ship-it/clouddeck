<?php

namespace Tests\Feature;

use App\Mail\MailSettingsTestMessage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    public function test_general_information_is_saved_and_shown_in_the_header(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'platform_name' => 'Acme Cloud',
            'support_email' => 'help@acme.test',
            'registration_enabled' => '1',
        ])->assertSessionHas('status');

        $this->assertSame('Acme Cloud', SystemSetting::whereKey('platform_name')->value('value'));
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Acme Cloud');
    }

    public function test_a_logo_is_stored_rendered_and_replaced_without_leaving_the_old_file_behind(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHas('status');
        $first = SystemSetting::whereKey('logo_path')->value('value');
        Storage::disk('public')->assertExists($first);

        $this->actingAs($admin)->post('/admin/settings/logo', ['logo' => UploadedFile::fake()->image('new.png')])->assertSessionHas('status');
        $second = SystemSetting::whereKey('logo_path')->value('value');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee(Storage::disk('public')->url($second), false);

        $this->actingAs($admin)->delete('/admin/settings/logo')->assertSessionHas('status');
        Storage::disk('public')->assertMissing($second);
    }

    public function test_the_logo_upload_rejects_files_that_are_not_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/settings/logo', ['logo' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php')])
            ->assertSessionHasErrors('logo');

        $this->assertNull(SystemSetting::whereKey('logo_path')->value('value'));
    }

    public function test_smtp_settings_drive_the_mail_config_and_keep_the_password_private(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings/mail', [
            'mail_host' => 'smtp.resend.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'resend',
            'mail_password' => 're_secret_key',
            'mail_from_address' => 'noreply@acme.test',
            'mail_from_name' => 'Acme',
        ])->assertSessionHas('status');

        $stored = SystemSetting::whereKey('mail_password')->sole();
        $this->assertSame('re_secret_key', $stored->value);
        $this->assertFalse($stored->is_public);
        $this->assertNotSame('re_secret_key', \DB::table('system_settings')->where('key', 'mail_password')->value('value'));

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertSame('smtp.resend.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('re_secret_key', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@acme.test', config('mail.from.address'));
    }

    public function test_saving_other_mail_fields_does_not_wipe_the_stored_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/settings/mail', ['mail_host' => 'smtp.resend.com', 'mail_password' => 're_secret_key']);

        // The form cannot repopulate a password field, so a blank one has to mean "keep it".
        $this->actingAs($admin)->put('/admin/settings/mail', ['mail_host' => 'smtp.example.com', 'mail_password' => '']);

        $this->assertSame('re_secret_key', SystemSetting::whereKey('mail_password')->value('value'));
        $this->assertSame('smtp.example.com', SystemSetting::whereKey('mail_host')->value('value'));
    }

    public function test_a_test_message_is_sent_and_refused_before_a_host_exists(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/mail/test', ['test_email' => 'someone@acme.test'])->assertSessionHasErrors('test_email');

        app(SystemSettings::class)->put('mail_host', 'smtp.resend.com');

        $this->actingAs($admin)->post('/admin/settings/mail/test', ['test_email' => 'someone@acme.test'])->assertSessionHas('status');
        Mail::assertSent(MailSettingsTestMessage::class, fn ($mail) => $mail->hasTo('someone@acme.test'));
    }

    public function test_mail_settings_are_left_alone_when_no_host_is_configured(): void
    {
        config(['mail.mailers.smtp.host' => 'from-env.example.com']);

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        // .env keeps working on an instance whose operator never opened the settings page.
        $this->assertSame('from-env.example.com', config('mail.mailers.smtp.host'));
    }

    public function test_settings_are_not_writable_by_a_customer(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->put('/admin/settings', ['platform_name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($customer)->put('/admin/settings/mail', ['mail_host' => 'evil.example.com'])->assertForbidden();
        $this->actingAs($customer)->post('/admin/settings/mail/test', ['test_email' => 'a@b.test'])->assertForbidden();
    }
}
