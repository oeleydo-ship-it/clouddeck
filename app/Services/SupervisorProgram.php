<?php

namespace App\Services;

/**
 * Canonical Supervisor program names for queue / Horizon / Reverb workers.
 *
 * Sync scripts write clouddeck-{id}; status checks also accept legacy Uplary-{id}.
 */
final class SupervisorProgram
{
    public const PREFIX = 'clouddeck-';

    public const LEGACY_PREFIX = 'Uplary-';

    public static function name(string $workerId): string
    {
        return self::PREFIX.$workerId;
    }

    /**
     * Prefer the sync-script name first, then the legacy rename that status jobs briefly used.
     *
     * @return list<string>
     */
    public static function candidates(string $workerId): array
    {
        return [
            self::name($workerId),
            self::LEGACY_PREFIX.$workerId,
        ];
    }

    /**
     * @return list<string>
     */
    public static function knownStates(): array
    {
        return ['RUNNING', 'STARTING', 'BACKOFF', 'STOPPING', 'STOPPED', 'EXITED', 'FATAL'];
    }

    public static function parseStatus(string $output, string $workerId): string
    {
        $states = implode('|', self::knownStates());

        foreach (self::candidates($workerId) as $program) {
            if (preg_match('/^'.preg_quote($program, '/').':\S+\s+('.$states.')\b/m', $output, $match)) {
                return $match[1];
            }
        }

        if (preg_match('/\b('.$states.')\b/', $output, $match)) {
            return $match[1];
        }

        return 'unknown';
    }

    public static function statusCommand(string $workerId): string
    {
        $parts = [];
        foreach (self::candidates($workerId) as $program) {
            $parts[] = 'supervisorctl status '.escapeshellarg($program.':*').' 2>&1 || true';
        }

        return implode('; ', $parts);
    }
}
