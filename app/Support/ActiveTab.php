<?php

namespace App\Support;

/**
 * Keeps the active panel on tabbed site/server pages across POST redirects.
 * Fragments (#queue) are not sent in Referer; a ?tab= query string is.
 */
class ActiveTab
{
    public static function sanitize(mixed $tab): ?string
    {
        if (! is_string($tab) || $tab === '') {
            return null;
        }

        return preg_match('/^[a-z][a-z0-9_-]{0,40}$/', $tab) === 1 ? $tab : null;
    }

    public static function isTabbedPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return (bool) preg_match('#^/sites/[0-9a-f-]{36}$#i', $path)
            || (bool) preg_match('#^/servers/[0-9a-f-]{36}/manage$#i', $path)
            || $path === '/notifications';
    }

    public static function append(string $url, string $tab): string
    {
        $tab = self::sanitize($tab);
        if ($tab === null) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || ! self::isTabbedPath($parts['path'] ?? null)) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        if (isset($query['tab']) && is_string($query['tab']) && $query['tab'] !== '') {
            return $url;
        }

        $query['tab'] = $tab;
        $rebuilt = ($parts['scheme'] ?? null) && ($parts['host'] ?? null)
            ? ($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''))
            : '';
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?'.http_build_query($query);
        if (! empty($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
