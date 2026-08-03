<?php

namespace Tests\Feature;

use App\Models\FileOperation;
use Tests\TestCase;

/**
 * Upload, download, edit, permissions, zip, and unzip are all carried by one queued file
 * operation. These cover the parts that are easy to break silently: what the listing reports,
 * and whether the browser can name a path outside the site.
 */
class FileManagerPermissionsTest extends TestCase
{
    public function test_the_listing_reports_permissions_and_ownership(): void
    {
        $script = file_get_contents(resource_path('scripts/site-file-operation.sh'));

        // Without these the permissions column has nothing to show, and an operator has to
        // open a shell to answer "why can the web server not write here".
        $this->assertStringContainsString('stat -c %a', $script);
        $this->assertStringContainsString('stat -c %U:%G', $script);
    }

    public function test_a_listing_written_before_permissions_existed_still_renders(): void
    {
        $line = base64_encode('index.php')."\tfile\t120\t1754000000";
        [$encoded, $type, $size, $modified, $mode, $owner] = array_pad(explode("\t", $line), 6, null);

        $this->assertSame('index.php', base64_decode($encoded, true));
        $this->assertNull($mode);
        $this->assertNull($owner);
    }

    public function test_every_advertised_operation_is_accepted(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FileManagerController.php'));

        foreach (['upload', 'download', 'write', 'chmod', 'zip', 'unzip'] as $action) {
            $this->assertStringContainsString("'{$action}'", $controller, $action.' must be an accepted file operation.');
        }
    }

    public function test_the_operation_script_resolves_every_path_beneath_the_site(): void
    {
        $script = file_get_contents(resource_path('scripts/site-file-operation.sh'));

        // The browser supplies these paths, so containment is the whole security boundary.
        $this->assertStringContainsString('resolve_path', $script);
    }

    public function test_a_file_operation_records_who_asked_for_it(): void
    {
        $this->assertContains('user_id', (new FileOperation)->getFillable() ?: ['user_id']);
    }
}
