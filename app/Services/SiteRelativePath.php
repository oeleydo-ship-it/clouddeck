<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class SiteRelativePath
{
    public function normalize(?string $path): string
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = trim($path, '/');
        $path = $path === '' ? '.' : $path;
        $segments = explode('/', $path);
        if (strlen($path) > 500 || str_contains($path, "\0") || preg_match('/[\x00-\x1F\x7F]/', $path) || in_array('..', $segments, true)) {
            throw ValidationException::withMessages(['path' => 'Use a relative path inside the site root.']);
        }

        return $path;
    }
}
