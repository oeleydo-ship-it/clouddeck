<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformSslProbe;

/**
 * Deterministic TLS probe for feature tests (no real sockets).
 */
final class FakePlatformSslProbe implements PlatformSslProbe
{
    /**
     * @var array{
     *     reachable: bool,
     *     verified: bool,
     *     subject: string|null,
     *     issuer: string|null,
     *     valid_from: string|null,
     *     valid_to: string|null,
     *     days_remaining: int|null,
     *     error: string|null
     * }
     */
    public array $result = [
        'reachable' => true,
        'verified' => true,
        'subject' => 'app.example.test',
        'issuer' => "Let's Encrypt",
        'valid_from' => null,
        'valid_to' => null,
        'days_remaining' => 60,
        'error' => null,
    ];

    /** @var list<array{host: string, port: int, timeout: int}> */
    public array $calls = [];

    public function probe(string $host, int $port = 443, int $timeoutSeconds = 5): array
    {
        $this->calls[] = ['host' => $host, 'port' => $port, 'timeout' => $timeoutSeconds];

        $result = $this->result;

        if ($result['valid_from'] === null && ($result['days_remaining'] ?? null) !== null) {
            $result['valid_from'] = now()->subDays(30)->toIso8601String();
            $result['valid_to'] = now()->addDays((int) $result['days_remaining'])->toIso8601String();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $this->result = array_merge($this->result, $overrides);

        return $this;
    }
}
