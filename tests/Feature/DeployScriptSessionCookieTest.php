<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sites get APP_NAME = their domain, and Laravel names the session cookie after
 * APP_NAME. PHP renames cookies with dots on the way in, so the deploy pins a
 * safe SESSION_COOKIE, or nobody could sign in to the deployed app.
 */
class DeployScriptSessionCookieTest extends TestCase
{
    private function script(): string
    {
        return file_get_contents(resource_path('scripts/deploy-laravel.sh'));
    }

    public function test_a_safe_session_cookie_name_is_pinned_after_the_env_is_written(): void
    {
        $script = $this->script();

        $written = strpos($script, '> "${SHARED}/.env"');
        $pinned = strpos($script, "\nensure_session_cookie_name\n");
        $composer = strpos($script, "\ninstall_composer_dependencies\n");

        $this->assertNotFalse($pinned);
        $this->assertLessThan($pinned, $written);
        $this->assertLessThan($composer, $pinned);
    }

    public function test_it_only_applies_to_dotted_app_names_without_a_chosen_cookie(): void
    {
        $script = $this->script();

        $this->assertStringContainsString("grep -qE '^SESSION_COOKIE=' \"\${env_file}\" && return 0", $script);
        $this->assertStringContainsString('*.*) printf', $script);
        $this->assertStringContainsString("tr -c 'A-Za-z0-9_-' '_'", $script);
    }
}
