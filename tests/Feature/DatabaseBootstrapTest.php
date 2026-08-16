<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Services\SystemSettings;
use App\Support\DatabaseBootstrap;
use Tests\TestCase;

class DatabaseBootstrapTest extends TestCase
{
    /** @var list<string>|null */
    private ?array $originalArgv = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalArgv = $_SERVER['argv'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }

        parent::tearDown();
    }

    public function test_app_service_provider_boot_survives_without_a_database_file(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('missing-boot-test.sqlite'),
        ]);

        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->app->make(AppServiceProvider::class, ['app' => $this->app])->boot();

        $this->assertTrue(true);
    }

    public function test_system_settings_returns_defaults_when_database_is_unavailable(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('missing-settings-test.sqlite'),
        ]);

        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->assertSame('fallback-name', app(SystemSettings::class)->get('platform_name', 'fallback-name'));
    }

    public function test_database_bootstrap_detects_composer_autoload_commands(): void
    {
        $_SERVER['argv'] = ['artisan', 'package:discover'];

        $this->assertTrue(DatabaseBootstrap::runningComposerBootCommand());
        $this->assertTrue(DatabaseBootstrap::shouldDeferDatabaseAccess());
    }
}
