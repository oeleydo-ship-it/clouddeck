<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformSslProbe;
use Carbon\Carbon;
use Throwable;

/**
 * Reads the peer certificate via a TLS client handshake (no HTTP body required).
 */
final class NativePlatformSslProbe implements PlatformSslProbe
{
    public function probe(string $host, int $port = 443, int $timeoutSeconds = 5): array
    {
        $empty = [
            'reachable' => false,
            'verified' => false,
            'subject' => null,
            'issuer' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'error' => null,
        ];

        if ($host === '' || $port <= 0) {
            return [...$empty, 'error' => 'Invalid host or port.'];
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ],
        ]);

        try {
            $client = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($client === false) {
                // Retry without verify so operators still see expiry/issuer on broken chains.
                return $this->probeUnverified($host, $port, $timeoutSeconds, $errno, $errstr);
            }

            $params = stream_context_get_params($client);
            fclose($client);

            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $cert) {
                return [...$empty, 'reachable' => true, 'error' => 'Connected but no peer certificate was returned.'];
            }

            return $this->parseCertificate($cert, verified: true);
        } catch (Throwable $e) {
            return [...$empty, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
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
    private function probeUnverified(string $host, int $port, int $timeoutSeconds, int $errno, string $errstr): array
    {
        $empty = [
            'reachable' => false,
            'verified' => false,
            'subject' => null,
            'issuer' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'error' => null,
        ];

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ],
        ]);

        try {
            $client = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno2,
                $errstr2,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($client === false) {
                $message = trim($errstr ?: $errstr2) ?: "TLS connection failed ({$errno})";

                return [...$empty, 'error' => mb_substr($message, 0, 200)];
            }

            $params = stream_context_get_params($client);
            fclose($client);

            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $cert) {
                return [...$empty, 'reachable' => true, 'error' => 'Connected but certificate verification failed and no cert was captured.'];
            }

            $parsed = $this->parseCertificate($cert, verified: false);
            $parsed['error'] = trim($errstr) ?: 'Certificate chain could not be verified.';

            return $parsed;
        } catch (Throwable $e) {
            return [...$empty, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * @param  resource  $cert
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
    private function parseCertificate($cert, bool $verified): array
    {
        $info = openssl_x509_parse($cert) ?: [];

        $validFrom = isset($info['validFrom_time_t'])
            ? Carbon::createFromTimestampUTC((int) $info['validFrom_time_t'])->toIso8601String()
            : null;
        $validTo = isset($info['validTo_time_t'])
            ? Carbon::createFromTimestampUTC((int) $info['validTo_time_t'])->toIso8601String()
            : null;

        $daysRemaining = null;
        if (isset($info['validTo_time_t'])) {
            $daysRemaining = (int) floor((((int) $info['validTo_time_t']) - time()) / 86400);
        }

        $subject = $this->formatDn($info['subject'] ?? null);
        $issuer = $this->formatDn($info['issuer'] ?? null);

        return [
            'reachable' => true,
            'verified' => $verified,
            'subject' => $subject,
            'issuer' => $issuer,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'days_remaining' => $daysRemaining,
            'error' => null,
        ];
    }

    private function formatDn(mixed $dn): ?string
    {
        if (! is_array($dn) || $dn === []) {
            return null;
        }

        if (isset($dn['CN']) && is_string($dn['CN']) && $dn['CN'] !== '') {
            return $dn['CN'];
        }

        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = $key.'='.$value;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
