<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformProcessLauncher;

/**
 * Test double that records start/stop intent without spawning daemons.
 */
final class FakePlatformProcessLauncher implements PlatformProcessLauncher
{
    /** @var array<int, true> */
    private array $running = [];

    private int $nextPid = 40_000;

    /** @var list<array{service: string, arguments: list<string>, pid: int}> */
    public array $started = [];

    /** @var list<int> */
    public array $stopped = [];

    /** @var list<list<string>> */
    public array $ran = [];

    /** @var list<array{exit_code: int, output: string, error: string}> */
    public array $runQueue = [];

    /** @var array{exit_code: int, output: string, error: string}|null */
    public ?array $nextRunResult = null;

    public function startArtisan(string $service, array $artisanArguments): int
    {
        $pid = $this->nextPid++;
        $this->running[$pid] = true;
        $this->started[] = [
            'service' => $service,
            'arguments' => $artisanArguments,
            'pid' => $pid,
        ];

        return $pid;
    }

    public function stop(int $pid): bool
    {
        $this->stopped[] = $pid;

        if (! isset($this->running[$pid])) {
            return false;
        }

        unset($this->running[$pid]);

        return true;
    }

    public function isRunning(int $pid): bool
    {
        return isset($this->running[$pid]);
    }

    public function run(array $command, ?int $timeoutSeconds = 15): array
    {
        $this->ran[] = $command;

        if ($this->runQueue !== []) {
            return array_shift($this->runQueue);
        }

        if ($this->nextRunResult !== null) {
            $result = $this->nextRunResult;
            $this->nextRunResult = null;

            return $result;
        }

        return [
            'exit_code' => 1,
            'output' => '',
            'error' => 'docker unavailable in fake launcher',
        ];
    }

    /**
     * @param  array{exit_code: int, output: string, error: string}  $result
     */
    public function enqueueRunResult(array $result): void
    {
        $this->runQueue[] = $result;
    }

    public function markRunning(int $pid): void
    {
        $this->running[$pid] = true;
    }
}
