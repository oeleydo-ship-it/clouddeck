<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployScriptDatabaseEnforcementTest extends TestCase
{
    private function script(): string
    {
        return file_get_contents(resource_path('scripts/deploy-laravel.sh'));
    }

    public function test_deploy_script_validates_database_env_before_composer_install(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('validate_database_env', $script);
        $this->assertStringContainsString('DB_CONNECTION is not set in .env', $script);
        $this->assertStringContainsString('fall back to SQLite', $script);

        $composerPos = strpos($script, 'composer install');
        $validatePos = strpos($script, 'validate_database_env');

        $this->assertNotFalse($composerPos);
        $this->assertNotFalse($validatePos);
        $this->assertLessThan($composerPos, $validatePos);
    }

    public function test_deploy_script_requires_credentials_for_managed_drivers(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('DB_HOST DB_DATABASE DB_USERNAME', $script);
        $this->assertStringContainsString('mysql|pgsql|mariadb', $script);
    }
}
