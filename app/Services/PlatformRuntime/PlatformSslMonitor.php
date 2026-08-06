<?php

namespace App\Services\PlatformRuntime;

use App\Services\PlatformRuntime\Contracts\PlatformProcessLauncher;
use App\Services\PlatformRuntime\Contracts\PlatformSslProbe;
use Throwable;

/**
 * Control-plane HTTPS status and optional Certbot renew for APP_URL's host.
 */
final class PlatformSslMonitor
{
    public function __construct(
        private readonly PlatformSslProbe $probe,
        private readonly PlatformProcessLauncher $launcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $windows = $this->isWindows();
        $appUrl = (string) config('app.url', '');
        $parsed = parse_url($appUrl) ?: [];
        $scheme = strtolower((string) ($parsed['scheme'] ?? 'http'));
        $host = (string) ($parsed['host'] ?? '');
        $port = (int) ($parsed['port'] ?? ($scheme === 'https' ? 443 : 80));
        $localHost = $this->isLocalHost($host);
        $warnDays = (int) config('platform-services.ssl.warn_days', 30);
        $timeout = (int) config('platform-services.ssl.probe_timeout', 5);
        $docsUrl = (string) config('platform-services.ssl.docs_url', '');
        $scriptPath = $this->renewScriptPath();
        $scriptPresent = is_string($scriptPath) && is_file($scriptPath);
        $certbotPresent = ! $windows && $this->commandAvailable('certbot');
        $liveCertPath = $host !== '' ? "/etc/letsencrypt/live/{$host}" : null;
        $liveCertOnDisk = ! $windows && $liveCertPath !== null && is_dir($liveCertPath);

        $base = [
            'key' => 'ssl',
            'name' => 'SSL / TLS',
            'app_url' => $appUrl,
            'domain' => $host !== '' ? $host : null,
            'scheme' => $scheme !== '' ? $scheme : null,
            'port' => $port > 0 ? $port : null,
            'windows' => $windows,
            'local_host' => $localHost,
            'docs_url' => $docsUrl !== '' ? $docsUrl : null,
            'meta' => [
                'warn_days' => $warnDays,
                'renew_script' => $scriptPath,
                'renew_script_present' => $scriptPresent,
                'certbot_present' => $certbotPresent,
                'live_cert_on_disk' => $liveCertOnDisk,
                'issuer' => null,
                'subject' => null,
                'valid_from' => null,
                'valid_to' => null,
                'days_remaining' => null,
                'verified' => null,
            ],
            'actions' => [
                'renew' => false,
            ],
            'last_error' => null,
        ];

        if ($host === '') {
            return [
                ...$base,
                'status' => 'not_https',
                'detail' => 'APP_URL has no hostname — set APP_URL to the public HTTPS origin.',
                'note' => $this->environmentNote($windows, $localHost, scriptPresent: false),
            ];
        }

        if ($scheme !== 'https') {
            $detail = $localHost
                ? "APP_URL is {$scheme}://{$host} — local `artisan serve` is HTTP-only; TLS is not managed here."
                : "APP_URL uses {$scheme}:// — point APP_URL at https://{$host} behind nginx (or similar) for TLS.";

            return [
                ...$base,
                'status' => 'not_https',
                'detail' => $detail,
                'note' => $this->environmentNote($windows, $localHost, $scriptPresent),
            ];
        }

        $probePort = $port > 0 ? $port : 443;
        $result = $this->probe->probe($host, $probePort, $timeout);

        $days = $result['days_remaining'];
        $status = match (true) {
            ! $result['reachable'] => 'unreachable',
            $days !== null && $days < 0 => 'expired',
            $days !== null && $days < $warnDays => 'expiring_soon',
            $result['reachable'] && $days !== null => 'valid',
            $result['reachable'] => 'valid',
            default => 'unreachable',
        };

        $detail = match ($status) {
            'unreachable' => 'HTTPS unreachable'.($result['error'] ? ' · '.$result['error'] : ''),
            'expired' => sprintf('Certificate expired%s', $days !== null ? ' · '.abs($days).'d ago' : ''),
            'expiring_soon' => sprintf(
                'Expiring soon · %dd remaining · issuer %s',
                $days ?? 0,
                $result['issuer'] ?? 'unknown'
            ),
            'valid' => sprintf(
                'Valid · %s remaining · issuer %s',
                $days !== null ? $days.'d' : 'unknown',
                $result['issuer'] ?? 'unknown'
            ),
            default => 'Unknown SSL state',
        };

        if ($result['verified'] === false && $status !== 'unreachable' && $result['error']) {
            $detail .= ' · chain not verified';
        }

        // Renew is offered when the documented local script exists on a non-local Linux host.
        $canRenew = ! $windows && ! $localHost && $scriptPresent;

        return [
            ...$base,
            'status' => $status,
            'detail' => $detail,
            'note' => $this->environmentNote($windows, $localHost, $scriptPresent),
            'meta' => [
                ...$base['meta'],
                'issuer' => $result['issuer'],
                'subject' => $result['subject'],
                'valid_from' => $result['valid_from'],
                'valid_to' => $result['valid_to'],
                'days_remaining' => $days,
                'verified' => $result['verified'],
            ],
            'actions' => [
                'renew' => $canRenew,
            ],
            'last_error' => $result['error'],
        ];
    }

    /**
     * @return array{ok: bool, message: string, ssl: array<string, mixed>}
     */
    public function renew(): array
    {
        $current = $this->status();
        $windows = $this->isWindows();
        $host = (string) ($current['domain'] ?? '');

        if ($windows) {
            return [
                'ok' => false,
                'message' => 'Certbot renew is not available on Windows. Use status against a remote HTTPS APP_URL, or renew on the Linux host.',
                'ssl' => $current,
            ];
        }

        if ($this->isLocalHost($host) || $host === '') {
            return [
                'ok' => false,
                'message' => 'Cannot renew TLS for a local or empty hostname. Set APP_URL to the public domain.',
                'ssl' => $current,
            ];
        }

        $scriptPath = $this->renewScriptPath();

        if (! is_string($scriptPath) || ! is_file($scriptPath)) {
            return [
                'ok' => false,
                'message' => 'Renew script is missing. Expected resources/scripts/renew-platform-ssl.sh (or PLATFORM_SSL_RENEW_SCRIPT).',
                'ssl' => $current,
            ];
        }

        $email = (string) (
            config('platform-services.ssl.email')
            ?: config('mail.from.address')
            ?: 'admin@'.$host
        );

        $tmp = storage_path('app/platform-services/renew-platform-ssl-'.uniqid('', true).'.sh');

        try {
            if (! is_dir(dirname($tmp))) {
                mkdir(dirname($tmp), 0755, true);
            }

            $body = file_get_contents($scriptPath);
            if ($body === false) {
                return ['ok' => false, 'message' => 'Could not read renew script.', 'ssl' => $current];
            }

            $rendered = str_replace(
                ['{{DOMAIN}}', '{{EMAIL}}'],
                [$host, $email],
                $body
            );

            if (file_put_contents($tmp, $rendered) === false) {
                return ['ok' => false, 'message' => 'Could not write temporary renew script.', 'ssl' => $current];
            }

            @chmod($tmp, 0700);

            $result = $this->launcher->run(['bash', $tmp], 300);

            if ($result['exit_code'] !== 0) {
                $message = trim($result['error'] ?: $result['output']) ?: 'Certbot renew failed.';

                return [
                    'ok' => false,
                    'message' => mb_substr($message, 0, 400),
                    'ssl' => $this->status(),
                ];
            }

            return [
                'ok' => true,
                'message' => 'SSL renew script completed. Re-check status for the new expiry.',
                'ssl' => $this->status(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => mb_substr($e->getMessage(), 0, 400),
                'ssl' => $this->status(),
            ];
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function renewScriptPath(): ?string
    {
        $configured = config('platform-services.ssl.renew_script');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = resource_path('scripts/renew-platform-ssl.sh');

        return is_file($default) ? $default : null;
    }

    private function isWindows(): bool
    {
        $override = config('platform-services.ssl.treat_as_windows');

        if ($override !== null) {
            return (bool) $override;
        }

        return PHP_OS_FAMILY === 'Windows';
    }

    private function isLocalHost(string $host): bool
    {
        $override = config('platform-services.ssl.treat_as_local_host');

        if ($override !== null) {
            return (bool) $override;
        }

        $host = strtolower(trim($host));

        if ($host === '') {
            return true;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');
    }

    private function environmentNote(bool $windows, bool $localHost, bool $scriptPresent): string
    {
        if ($windows) {
            return 'Windows / local: Start/Stop N/A. Status probes APP_URL over HTTPS when remote; `php artisan serve` itself is HTTP-only.';
        }

        if ($localHost) {
            return 'This host looks local. Point APP_URL at the public HTTPS domain behind nginx before issuing Certbot certificates.';
        }

        if ($scriptPresent) {
            return 'Linux: Renew runs resources/scripts/renew-platform-ssl.sh (Certbot + nginx reload) for this control-plane domain only — not customer sites.';
        }

        return 'Linux: Install Certbot and ensure renew-platform-ssl.sh is present to enable Renew.';
    }

    private function commandAvailable(string $binary): bool
    {
        foreach (['/usr/bin/', '/usr/local/bin/', '/bin/', '/sbin/', '/usr/sbin/'] as $prefix) {
            if (is_executable($prefix.$binary)) {
                return true;
            }
        }

        $result = $this->launcher->run(['bash', '-lc', 'command -v '.escapeshellarg($binary)], 5);

        return $result['exit_code'] === 0 && trim($result['output']) !== '';
    }
}
