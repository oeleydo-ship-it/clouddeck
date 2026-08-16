<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guards application boot paths that should not touch the database during Composer
 * autoload scripts (package:discover) or before .env / the DB file is available.
 */
final class DatabaseBootstrap
{
    /**
     * Artisan commands Composer runs before deploy has linked .env or run migrations.
     *
     * @var list<string>
     */
    private const COMPOSER_BOOT_COMMANDS = [
        'package:discover',
        'vendor:publish',
    ];

    public static function shouldDeferDatabaseAccess(): bool
    {
        return static::runningComposerBootCommand() || ! static::isAvailable();
    }

    public static function runningComposerBootCommand(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];

        return in_array($argv[1] ?? '', self::COMPOSER_BOOT_COMMANDS, true);
    }

    public static function isAvailable(): bool
    {
        try {
            $default = (string) config('database.default', 'sqlite');

            if ($default === 'sqlite') {
                $path = (string) config('database.connections.sqlite.database', '');

                if ($path !== ':memory:'
                    && ! str_contains($path, '?mode=memory')
                    && ! str_contains($path, '&mode=memory')
                ) {
                    $resolved = realpath($path) ?: realpath(base_path($path));

                    if ($resolved === false) {
                        return false;
                    }
                }
            }

            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
