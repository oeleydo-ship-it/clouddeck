<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Composer's GitHub zip downloads (api.github.com) can time out with HTTP 504, and
 * recent Composer no longer falls back to git on its own. The deploy must survive it.
 */
class DeployScriptComposerInstallTest extends TestCase
{
    private function script(): string
    {
        return file_get_contents(resource_path('scripts/deploy-laravel.sh'));
    }

    public function test_composer_install_is_retried_with_limited_parallelism(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('install_composer_dependencies', $script);
        $this->assertStringContainsString('for attempt in 1 2 3', $script);
        $this->assertStringContainsString('COMPOSER_MAX_PARALLEL_HTTP', $script);
    }

    public function test_it_falls_back_to_source_installs_after_the_retries(): void
    {
        $script = $this->script();

        $dist = strpos($script, 'composer install --no-dev --no-interaction --prefer-dist');
        $source = strpos($script, 'composer install --no-dev --no-interaction --prefer-source');

        $this->assertNotFalse($dist);
        $this->assertNotFalse($source);
        $this->assertLessThan($source, $dist);
    }

    public function test_a_github_repository_token_authenticates_composer_and_is_cleared(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('https://github.com/*)', $script);
        $this->assertStringContainsString('github-oauth', $script);
        $this->assertStringContainsString('unset COMPOSER_AUTH', $script);
    }
}
