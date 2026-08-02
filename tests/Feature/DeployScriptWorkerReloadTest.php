<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A worker that is never recycled keeps serving the release it started with. Every job class
 * added after that point fails to unserialize on arrival — "__PHP_Incomplete_Class" — which
 * gives no usable error and leaves the record that queued it stuck forever.
 */
class DeployScriptWorkerReloadTest extends TestCase
{
    private function script(): string
    {
        return file_get_contents(resource_path('scripts/deploy-laravel.sh'));
    }

    public function test_plain_queue_workers_are_recycled_after_the_release_switch(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('queue:restart', $script);
        $this->assertGreaterThan(
            strpos($script, 'Switching the current release atomically'),
            strpos($script, 'queue:restart'),
            'Workers must be recycled after the symlink moves, or they reload the release being replaced.'
        );
    }

    public function test_horizon_is_terminated_too_because_it_ignores_queue_restart(): void
    {
        // This is the bug that shipped: queue:restart alone left every Horizon-backed site
        // running the previous release indefinitely.
        $this->assertStringContainsString('horizon:terminate', $this->script());
    }

    public function test_reverb_is_recycled_as_well(): void
    {
        $this->assertStringContainsString('reverb:start', $this->script());
    }
}
