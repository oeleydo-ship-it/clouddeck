<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Str;

final class ReverbEnvironment
{
    public function __construct(private readonly ServerPortRegistry $ports) {}

    /**
     * Write the full Reverb connection set into the site's encrypted environment, generating
     * credentials once and preserving anything the customer has already customised.
     */
    public function apply(Site $site, ?int $port = null): int
    {
        $existing = $site->environmentVariables()->pluck('value', 'key');
        $port ??= (int) ($existing['REVERB_SERVER_PORT'] ?? $this->ports->allocate($site->server, ServerPortRegistry::REVERB_DEFAULT));

        // Nginx proxies the WebSocket on the site's own domain, so browsers always use the
        // site's scheme and standard port. Anything else is mixed content on an HTTPS page.
        $secure = $site->sslCertificates()->where('status', 'active')->exists();
        $scheme = $secure ? 'https' : 'http';
        $publicPort = $secure ? 443 : 80;

        $values = [
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => $existing['REVERB_APP_ID'] ?? (string) random_int(100000, 999999),
            'REVERB_APP_KEY' => $existing['REVERB_APP_KEY'] ?? Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => $existing['REVERB_APP_SECRET'] ?? Str::lower(Str::random(20)),
            // Bind address for the server process: loopback only, never exposed directly.
            'REVERB_SERVER_HOST' => '127.0.0.1',
            'REVERB_SERVER_PORT' => (string) $port,
            // What browsers connect to.
            'REVERB_HOST' => $existing['REVERB_HOST'] ?? $site->domain,
            'REVERB_PORT' => (string) $publicPort,
            'REVERB_SCHEME' => $scheme,
        ];

        $values += [
            'VITE_REVERB_APP_KEY' => $values['REVERB_APP_KEY'],
            'VITE_REVERB_HOST' => $values['REVERB_HOST'],
            'VITE_REVERB_PORT' => (string) $publicPort,
            'VITE_REVERB_SCHEME' => $scheme,
        ];

        foreach ($values as $key => $value) {
            $site->environmentVariables()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'is_secret' => in_array($key, ['REVERB_APP_SECRET', 'REVERB_APP_KEY'], true)],
            );
        }

        return $port;
    }
}
