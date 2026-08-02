<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The public wordpress.org directory, so themes and plugins can be browsed and installed
 * by name rather than by knowing a slug in advance.
 */
final class WordPressDirectory
{
    /**
     * @return array<int, array{slug: string, name: string, author: string, rating: int, installs: int, preview: ?string, screenshot: ?string}>
     */
    public function themes(?string $search = null): array
    {
        return $this->query('themes', $search);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plugins(?string $search = null): array
    {
        return $this->query('plugins', $search);
    }

    private function query(string $type, ?string $search): array
    {
        // Cached because the directory is the same for every customer and this sits in a
        // page render: an outage there should not make the tab slow or broken.
        $key = 'wp-directory:'.$type.':'.md5((string) $search);

        return Cache::remember($key, now()->addHours(6), function () use ($type, $search) {
            try {
                $response = Http::timeout(8)->get("https://api.wordpress.org/{$type}/info/1.2/", [
                    'action' => 'query_'.$type,
                    'request' => [
                        'browse' => $search ? null : 'popular',
                        'search' => $search ?: null,
                        'per_page' => 12,
                        'fields' => ['screenshot_url' => true, 'rating' => true, 'active_installs' => true, 'short_description' => true],
                    ],
                ]);

                if ($response->failed()) {
                    return [];
                }

                return collect($response->json($type === 'themes' ? 'themes' : 'plugins') ?? [])
                    ->map(fn (array $item) => [
                        'slug' => (string) ($item['slug'] ?? ''),
                        'name' => strip_tags((string) ($item['name'] ?? '')),
                        'author' => strip_tags(is_array($item['author'] ?? null) ? ($item['author']['display_name'] ?? '') : (string) ($item['author'] ?? '')),
                        'rating' => (int) round(((float) ($item['rating'] ?? 0)) / 20),
                        'installs' => (int) ($item['active_installs'] ?? 0),
                        'description' => strip_tags((string) ($item['short_description'] ?? '')),
                        'screenshot' => $item['screenshot_url'] ?? null,
                    ])
                    ->filter(fn (array $item) => $item['slug'] !== '')
                    ->values()
                    ->all();
            } catch (Throwable) {
                // The directory being unreachable is not a reason for the page to fail;
                // installing by slug still works.
                return [];
            }
        });
    }
}
