<?php

namespace App\Services\PlatformRuntime\Contracts;

interface PlatformSslProbe
{
    /**
     * Capture the peer TLS certificate presented by host:port.
     *
     * @return array{
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
    public function probe(string $host, int $port = 443, int $timeoutSeconds = 5): array;
}
