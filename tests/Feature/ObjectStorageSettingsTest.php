<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\BackupStorage;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObjectStorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
    }

    public function test_superadmin_can_save_object_storage_and_config_resolves_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/storage')
            ->assertOk()
            ->assertSee('Object storage', false)
            ->assertSee('DigitalOcean Spaces', false);

        $this->actingAs($admin)->put('/admin/settings/object-storage', [
            'object_storage_provider' => 'digitalocean',
            'object_storage_key' => 'DOKEY123',
            'object_storage_secret' => 'DOSECRET123',
            'object_storage_region' => 'nyc3',
            'object_storage_bucket' => 'uplary-backups',
            'object_storage_endpoint' => 'https://nyc3.digitaloceanspaces.com',
            'object_storage_url' => '',
            'object_storage_path_style' => '0',
            'database_backup_disk' => 's3',
        ])->assertSessionHas('status');

        $settings = app(SystemSettings::class);
        $this->assertTrue($settings->objectStorageConfigured());
        $this->assertSame('s3', $settings->databaseBackupDisk());
        $this->assertFalse((bool) SystemSetting::whereKey('object_storage_secret')->value('is_public'));

        // Blank secrets keep stored values.
        $this->actingAs($admin)->put('/admin/settings/object-storage', [
            'object_storage_provider' => 'digitalocean',
            'object_storage_key' => '',
            'object_storage_secret' => '',
            'object_storage_region' => 'nyc3',
            'object_storage_bucket' => 'uplary-backups',
            'object_storage_endpoint' => 'https://nyc3.digitaloceanspaces.com',
            'object_storage_url' => '',
            'database_backup_disk' => 's3',
        ])->assertSessionHas('status');

        $this->assertSame('DOSECRET123', SystemSetting::whereKey('object_storage_secret')->value('value'));

        config([
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
            'filesystems.disks.s3.region' => null,
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.endpoint' => null,
            'remote_management.database_backup_disk' => 'local',
        ]);

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertSame('DOKEY123', config('filesystems.disks.s3.key'));
        $this->assertSame('DOSECRET123', config('filesystems.disks.s3.secret'));
        $this->assertSame('nyc3', config('filesystems.disks.s3.region'));
        $this->assertSame('uplary-backups', config('filesystems.disks.s3.bucket'));
        $this->assertSame('https://nyc3.digitaloceanspaces.com', config('filesystems.disks.s3.endpoint'));
        $this->assertSame('s3', config('remote_management.database_backup_disk'));
        $this->assertSame('s3', app(BackupStorage::class)->defaultDisk());
        $this->assertArrayHasKey('s3', app(BackupStorage::class)->privateDiskOptions());
    }

    public function test_cannot_default_to_s3_until_credentials_are_complete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/object-storage', [
            'object_storage_provider' => 'wasabi',
            'object_storage_key' => '',
            'object_storage_secret' => '',
            'object_storage_region' => 'us-east-1',
            'object_storage_bucket' => '',
            'object_storage_endpoint' => 'https://s3.us-east-1.wasabisys.com',
            'database_backup_disk' => 's3',
        ])->assertSessionHasErrors('database_backup_disk');
    }

    public function test_customers_cannot_change_object_storage_settings(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get('/admin/storage')->assertForbidden();
        $this->actingAs($customer)->put('/admin/settings/object-storage', [
            'object_storage_provider' => 'custom',
            'object_storage_region' => 'us-east-1',
            'object_storage_bucket' => 'stolen',
            'database_backup_disk' => 'local',
        ])->assertForbidden();
    }

    public function test_object_storage_connection_test_uses_s3_disk(): void
    {
        Storage::fake('s3');
        $admin = $this->admin();
        $settings = app(SystemSettings::class);
        $settings->put('object_storage_key', 'K', 'string', false);
        $settings->put('object_storage_secret', 'S', 'string', false);
        $settings->put('object_storage_region', 'nyc3', 'string', true);
        $settings->put('object_storage_bucket', 'bucket', 'string', true);
        $settings->put('object_storage_endpoint', 'https://nyc3.digitaloceanspaces.com', 'string', true);

        $this->actingAs($admin)->post('/admin/settings/object-storage/test')
            ->assertRedirect()
            ->assertSessionHas('status');
    }
}
