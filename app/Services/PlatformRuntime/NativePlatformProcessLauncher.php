<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformProcessLauncher;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Starts and stops local artisan daemons for the control plane, with PID tracking.
 */
final class NativePlatformProcessLauncher implements PlatformProcessLauncher
{
    public function startArtisan(string $service, array $artisanArguments): int
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $cwd = base_path();
        $stdoutLog = storage_path('logs/platform-'.$service.'.out.log');
        $stderrLog = storage_path('logs/platform-'.$service.'.err.log');

        if (! is_dir(dirname($stdoutLog))) {
            mkdir(dirname($stdoutLog), 0755, true);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->startWindows($php, $artisan, $artisanArguments, $cwd, $stdoutLog, $stderrLog);
        }

        return $this->startUnix($php, $artisan, $artisanArguments, $cwd, $stdoutLog);
    }

    public function stop(int $pid): bool
    {
        if ($pid <= 0 || ! $this->isRunning($pid)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $result = $this->run(['taskkill', '/PID', (string) $pid, '/T', '/F']);

            return $result['exit_code'] === 0 || ! $this->isRunning($pid);
        }

        if (function_exists('posix_kill')) {
            @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
            usleep(200_000);

            if ($this->isRunning($pid)) {
                @posix_kill($pid, defined('SIGKILL') ? SIGKILL : 9);
            }

            return ! $this->isRunning($pid);
        }

        $result = $this->run(['kill', '-TERM', (string) $pid]);

        return $result['exit_code'] === 0 || ! $this->isRunning($pid);
    }

    public function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $result = $this->run(['tasklist', '/FI', 'PID eq '.$pid, '/NH']);

            return $result['exit_code'] === 0
                && str_contains($result['output'], (string) $pid)
                && ! str_contains(strtolower($result['output']), 'no tasks');
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return file_exists("/proc/{$pid}");
    }

    public function run(array $command, ?int $timeoutSeconds = 15): array
    {
        $process = new Process($command);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'exit_code' => 1,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    /**
     * Build the PowerShell Start-Process script used on Windows.
     *
     * Stdout and stderr must use different redirect paths — PowerShell rejects identical ones.
     *
     * @param  list<string>  $artisanArguments
     */
    public function buildWindowsStartScript(
        string $php,
        string $artisan,
        array $artisanArguments,
        string $cwd,
        string $stdoutLog,
        string $stderrLog,
    ): string {
        if ($stdoutLog === $stderrLog) {
            throw new RuntimeException('Windows Start-Process requires distinct stdout and stderr redirect paths.');
        }

        $argumentList = collect([$artisan, ...$artisanArguments])
            ->map(fn (string $part) => $this->psSingleQuote($part))
            ->implode(',');

        return sprintf(
            "\$p = Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s -PassThru; Write-Output \$p.Id",
            $this->psSingleQuote($php),
            $argumentList,
            $this->psSingleQuote($cwd),
            $this->psSingleQuote($stdoutLog),
            $this->psSingleQuote($stderrLog),
        );
    }

    /**
     * @param  list<string>  $artisanArguments
     */
    private function startUnix(string $php, string $artisan, array $artisanArguments, string $cwd, string $log): int
    {
        $parts = array_merge(
            [escapeshellarg($php), escapeshellarg($artisan)],
            array_map('escapeshellarg', $artisanArguments)
        );

        $command = sprintf(
            'cd %s && nohup %s >> %s 2>&1 & echo $!',
            escapeshellarg($cwd),
            implode(' ', $parts),
            escapeshellarg($log)
        );

        $output = [];
        $exit = 0;
        exec($command, $output, $exit);

        $pid = (int) trim((string) ($output[0] ?? '0'));

        if ($exit !== 0 || $pid <= 0) {
            throw new RuntimeException('Failed to start detached artisan process on Unix.');
        }

        return $pid;
    }

    /**
     * @param  list<string>  $artisanArguments
     */
    private function startWindows(
        string $php,
        string $artisan,
        array $artisanArguments,
        string $cwd,
        string $stdoutLog,
        string $stderrLog,
    ): int {
        $script = $this->buildWindowsStartScript(
            $php,
            $artisan,
            $artisanArguments,
            $cwd,
            $stdoutLog,
            $stderrLog,
        );

        $result = $this->run(['powershell', '-NoProfile', '-Command', $script], 30);
        $pid = (int) trim($result['output']);

        if ($result['exit_code'] !== 0 || $pid <= 0) {
            $message = trim($result['error'] ?: $result['output']) ?: 'unknown PowerShell error';

            throw new RuntimeException('Failed to start detached artisan process on Windows: '.$message);
        }

        return $pid;
    }

    private function psSingleQuote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
