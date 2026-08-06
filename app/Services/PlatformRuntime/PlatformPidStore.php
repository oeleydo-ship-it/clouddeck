<?php

namespace App\Services\PlatformRuntime;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Tracks PIDs for control-plane processes started from the Platform services panel.
 */
final class PlatformPidStore
{
    public function __construct(
        private readonly ?string $directory = null,
    ) {}

    public function directory(): string
    {
        $path = $this->directory ?? (string) config('platform-services.pid_directory');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return $path;
    }

    public function path(string $service): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $service) ?: 'unknown';

        return $this->directory().DIRECTORY_SEPARATOR.$safe.'.pid';
    }

    public function read(string $service): ?int
    {
        $path = $this->path($service);

        if (! File::exists($path)) {
            return null;
        }

        $pid = (int) trim((string) File::get($path));

        return $pid > 0 ? $pid : null;
    }

    public function write(string $service, int $pid): void
    {
        if ($pid <= 0) {
            throw new RuntimeException("Invalid PID for {$service}.");
        }

        File::put($this->path($service), (string) $pid);
    }

    public function clear(string $service): void
    {
        $path = $this->path($service);

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
