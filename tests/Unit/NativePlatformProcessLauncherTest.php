<?php

namespace Tests\Unit;

use App\Services\PlatformRuntime\NativePlatformProcessLauncher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NativePlatformProcessLauncherTest extends TestCase
{
    public function test_windows_start_script_uses_distinct_stdout_and_stderr_redirects(): void
    {
        $launcher = new NativePlatformProcessLauncher;

        $php = 'C:\\Users\\Eleydo Family\\AppData\\Local\\Programs\\Herd\\bin\\php.bat';
        $artisan = 'C:\\Users\\Eleydo Family\\Documents\\laravelpluck\\artisan';
        $cwd = 'C:\\Users\\Eleydo Family\\Documents\\laravelpluck';
        $stdout = 'C:\\Users\\Eleydo Family\\Documents\\laravelpluck\\storage\\logs\\platform-queue.out.log';
        $stderr = 'C:\\Users\\Eleydo Family\\Documents\\laravelpluck\\storage\\logs\\platform-queue.err.log';

        $script = $launcher->buildWindowsStartScript(
            $php,
            $artisan,
            ['queue:work', '--sleep=1'],
            $cwd,
            $stdout,
            $stderr,
        );

        $this->assertStringContainsString("-RedirectStandardOutput '{$stdout}'", $script);
        $this->assertStringContainsString("-RedirectStandardError '{$stderr}'", $script);
        $this->assertNotSame($stdout, $stderr);

        preg_match("/-RedirectStandardOutput ('(?:[^']|'')*')/", $script, $outMatch);
        preg_match("/-RedirectStandardError ('(?:[^']|'')*')/", $script, $errMatch);

        $this->assertNotEmpty($outMatch[1] ?? null);
        $this->assertNotEmpty($errMatch[1] ?? null);
        $this->assertNotSame($outMatch[1], $errMatch[1]);

        $this->assertStringContainsString("-FilePath '{$php}'", $script);
        $this->assertStringContainsString("-WorkingDirectory '{$cwd}'", $script);
        $this->assertStringContainsString("'{$artisan}'", $script);
        $this->assertStringContainsString('Write-Output $p.Id', $script);
    }

    public function test_windows_start_script_rejects_identical_redirect_paths(): void
    {
        $launcher = new NativePlatformProcessLauncher;
        $same = 'C:\\logs\\platform-queue.log';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('distinct stdout and stderr');

        $launcher->buildWindowsStartScript(
            'php.exe',
            'artisan',
            ['queue:work'],
            'C:\\app',
            $same,
            $same,
        );
    }

    public function test_windows_start_script_escapes_single_quotes_in_paths(): void
    {
        $launcher = new NativePlatformProcessLauncher;

        $script = $launcher->buildWindowsStartScript(
            "C:\\O'Brien\\php.exe",
            "C:\\O'Brien\\artisan",
            ["queue:work"],
            "C:\\O'Brien\\app",
            "C:\\O'Brien\\out.log",
            "C:\\O'Brien\\err.log",
        );

        $this->assertStringContainsString("-FilePath 'C:\\O''Brien\\php.exe'", $script);
        $this->assertStringContainsString("-RedirectStandardOutput 'C:\\O''Brien\\out.log'", $script);
        $this->assertStringContainsString("-RedirectStandardError 'C:\\O''Brien\\err.log'", $script);
    }
}
