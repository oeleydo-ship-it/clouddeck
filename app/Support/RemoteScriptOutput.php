<?php

namespace App\Support;

/**
 * Parse marker lines emitted by remote bash scripts. Accepts both UPLARY_* and
 * legacy CLOUDDECK_* prefixes so existing servers keep working after renames.
 */
final class RemoteScriptOutput
{
    public static function value(string $key, string $output): ?string
    {
        if (preg_match('/(?:UPLARY|CLOUDDECK)_'.preg_quote($key, '/').'=(\S+)/', $output, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function capture(string $pattern, string $output): ?string
    {
        if (preg_match('/(?:UPLARY|CLOUDDECK)_'.$pattern.'/', $output, $match)) {
            return $match[1] ?? null;
        }

        return null;
    }
}
