<?php

namespace Tests\Unit;

use App\Support\ActiveTab;
use PHPUnit\Framework\TestCase;

class ActiveTabTest extends TestCase
{
    public function test_it_sanitizes_tab_identifiers(): void
    {
        $this->assertSame('queue', ActiveTab::sanitize('queue'));
        $this->assertSame('queue-workers', ActiveTab::sanitize('queue-workers'));
        $this->assertNull(ActiveTab::sanitize('Queue'));
        $this->assertNull(ActiveTab::sanitize('../evil'));
        $this->assertNull(ActiveTab::sanitize(''));
        $this->assertNull(ActiveTab::sanitize(null));
    }

    public function test_it_appends_tab_only_on_tabbed_paths_without_overwriting(): void
    {
        $site = 'https://uplary.test/sites/019fd27b-faa2-70d9-b71b-795da6e524ce';
        $remote = $site.'/remote?tab=terminal';
        $manage = 'https://uplary.test/servers/019fd27b-fa9d-7308-877e-c68121bfb608/manage';

        $this->assertSame($site.'?tab=queue', ActiveTab::append($site, 'queue'));
        $this->assertSame($site.'?tab=environment', ActiveTab::append($site.'?tab=environment', 'queue'));
        $this->assertSame($remote, ActiveTab::append($remote, 'queue'));
        $this->assertSame($manage.'?tab=workers', ActiveTab::append($manage, 'workers'));
        $this->assertSame('https://uplary.test/notifications?tab=email', ActiveTab::append('https://uplary.test/notifications', 'email'));
        $this->assertSame('https://uplary.test/deployments/abc', ActiveTab::append('https://uplary.test/deployments/abc', 'queue'));
    }
}
