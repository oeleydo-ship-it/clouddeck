<?php

namespace App\Services\PlatformRuntime\Contracts;

interface PlatformProcessLauncher
{
    /**
     * Start a detached artisan process and return its OS PID.
     *
     * @param  list<string>  $artisanArguments  Arguments after `artisan` (e.g. ['horizon']).
     */
    public function startArtisan(string $service, array $artisanArguments): int;

    /**
     * Terminate a process previously started (or tracked) by this panel.
     */
    public function stop(int $pid): bool;

    /**
     * Whether the given PID is still alive.
     */
    public function isRunning(int $pid): bool;

    /**
     * Run a short-lived command and return exit code, stdout, and stderr.
     *
     * @param  list<string>  $command
     * @return array{exit_code: int, output: string, error: string}
     */
    public function run(array $command, ?int $timeoutSeconds = 15): array;
}
