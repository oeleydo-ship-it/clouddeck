<?php

namespace App\Services;

use App\Models\QueueWorker;
use App\Models\Server;

final class ServerPortRegistry
{
    /**
     * Reverb's own framework default. phpMyAdmin must never be placed here, otherwise a manual
     * `php artisan reverb:start` (which takes no port) fails with EADDRINUSE.
     */
    public const REVERB_DEFAULT = 8080;

    public const PHPMYADMIN_DEFAULT = 8081;

    /**
     * @return array<int, string> port => what already occupies it
     */
    public function taken(Server $server, ?string $ignoreWorkerId = null): array
    {
        $taken = [];

        if ($server->phpmyadmin_enabled && $server->phpmyadmin_port) {
            $taken[(int) $server->phpmyadmin_port] = 'phpMyAdmin';
        }

        QueueWorker::query()
            ->whereIn('site_id', $server->sites()->pluck('id'))
            ->whereNotNull('port')
            ->when($ignoreWorkerId, fn ($query) => $query->whereKeyNot($ignoreWorkerId))
            ->get(['id', 'name', 'port'])
            ->each(function (QueueWorker $worker) use (&$taken): void {
                $taken[(int) $worker->port] = 'the "'.$worker->name.'" Reverb worker';
            });

        return $taken;
    }

    public function conflict(Server $server, int $port, ?string $ignoreWorkerId = null): ?string
    {
        return $this->taken($server, $ignoreWorkerId)[$port] ?? null;
    }

    /**
     * First free port at or after the preferred one, skipping anything already bound.
     */
    public function allocate(Server $server, int $preferred): int
    {
        $taken = $this->taken($server);
        $port = $preferred;
        while (isset($taken[$port]) && $port < 65535) {
            $port++;
        }

        return $port;
    }
}
