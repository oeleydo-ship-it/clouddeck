<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Guards application boot paths that should not touch the database during Composer
 * autoload scripts (package:discover) or before .env / the DB file is available.
 *
 * When DB_CONNECTION is mysql or pgsql, SQLite is never used as a silent fallback.
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

    /** @var list<string> */
    private const MANAGED_DRIVERS = ['mysql', 'pgsql', 'mariadb'];

    /** @var list<string> */
    private const MANAGED_CREDENTIAL_KEYS = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];

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

    /**
     * Read DB_CONNECTION from the environment without falling back to config's sqlite default.
     */
    public static function envConnection(): ?string
    {
        $connection = env('DB_CONNECTION');

        if ($connection === null || $connection === '') {
            return null;
        }

        return (string) $connection;
    }

    public static function requiresManagedDatabase(?string $connection = null): bool
    {
        $connection ??= static::envConnection() ?? (string) config('database.default', 'sqlite');

        return in_array($connection, self::MANAGED_DRIVERS, true);
    }

    /**
     * @param  array<string, string|null>  $environment
     * @return list<string>
     */
    public static function missingCredentialKeys(?string $connection = null, array $environment = []): array
    {
        $connection ??= static::envConnection() ?? (string) config('database.default', 'sqlite');

        if (! static::requiresManagedDatabase($connection)) {
            return [];
        }

        $missing = [];

        foreach (self::MANAGED_CREDENTIAL_KEYS as $key) {
            $value = array_key_exists($key, $environment)
                ? $environment[$key]
                : env($key);

            if (static::managedCredentialMissing($key, $value)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private static function managedCredentialMissing(string $key, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($key === 'DB_DATABASE' && is_string($value)) {
            return $value === ':memory:'
                || str_contains($value, '?mode=memory')
                || str_contains($value, '&mode=memory');
        }

        return false;
    }

    /**
     * Lock config to the configured driver and reject incomplete mysql/pgsql credentials
     * outside Composer autoload deferral windows.
     */
    public static function enforceConfiguredConnection(): void
    {
        $connection = static::envConnection();

        if ($connection === null) {
            return;
        }

        config(['database.default' => $connection]);

        if (static::requiresManagedDatabase($connection)) {
            $missing = static::missingCredentialKeys($connection);

            if ($missing !== [] && ! static::runningComposerBootCommand()) {
                throw new RuntimeException(
                    'Database connection "'.$connection.'" requires '
                    .implode(', ', $missing)
                    .' in the environment. SQLite fallback is not allowed when a managed database driver is configured.'
                );
            }
        }
    }

    public static function isAvailable(): bool
    {
        try {
            $default = static::envConnection() ?? (string) config('database.default', 'sqlite');

            if (static::requiresManagedDatabase($default)) {
                if (static::missingCredentialKeys($default) !== []) {
                    return false;
                }
            } elseif ($default === 'sqlite') {
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
