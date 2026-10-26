<?php

namespace Tests\Unit;

use App\Ssh\SshClient;
use Illuminate\Support\Facades\Process;
use ReflectionMethod;
use Tests\TestCase;

class SshClientTest extends TestCase
{
    public function test_configure_site_defaults_the_multisite_option_to_disabled(): void
    {
        $method = new ReflectionMethod(SshClient::class, 'script');
        $script = $method->invoke(new SshClient, resource_path('scripts/configure-site.sh'), [
            'DOMAIN' => 'example.com',
            'PHP_VERSION' => '8.4',
            'DOCUMENT_ROOT' => 'current/public',
            'PLATFORM' => 'laravel',
        ]);

        $this->assertMatchesRegularExpression('/MULTISITE_ORIGIN=[\'\"]0[\'\"]/', $script);
        $this->assertStringNotContainsString('{{MULTISITE_ORIGIN}}', $script);
    }

    public function test_windows_identity_uses_absolute_whoami_when_path_lookup_has_no_output(): void
    {
        Process::fake(function (object $process) {
            $executable = strtolower((string) ($process->command[0] ?? ''));

            if ($executable === 'whoami') {
                return Process::result(errorOutput: 'Command not found', exitCode: 1);
            }

            if (str_ends_with($executable, '\\system32\\whoami.exe')) {
                return Process::result(output: "WORKSTATION\\queue-user\n", exitCode: 0);
            }

            return Process::result(exitCode: 1);
        });

        $method = new ReflectionMethod(SshClient::class, 'windowsIdentity');

        $this->assertSame('WORKSTATION\\queue-user', $method->invoke(new SshClient));
        Process::assertRan(fn ($process) => strtolower((string) $process->command[0]) === 'whoami');
        Process::assertRan(fn ($process) => str_ends_with(strtolower((string) $process->command[0]), '\\system32\\whoami.exe'));
    }
}
