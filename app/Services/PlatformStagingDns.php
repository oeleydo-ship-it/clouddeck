<?php

namespace App\Services;

use App\Dns\Cloudflare\CloudflareDns;
use App\Dns\Exceptions\DnsCredentialException;
use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Creates per-tenant A records on the platform Cloudflare zone for
 * {slug}.staging.{platform-domain} → the site's server public IP.
 *
 * A single wildcard cannot serve multi-tenant control planes: each customer's
 * staging hostname must resolve to that customer's server.
 */
final class PlatformStagingDns
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function configured(): bool
    {
        return filled($this->settings->platformDnsCloudflareToken())
            && filled($this->settings->platformDnsCloudflareZoneId());
    }

    public function appliesTo(Site $site): bool
    {
        return $site->isStaging()
            && ($site->domain_source ?? null) === 'platform'
            && filled($site->staging_slug);
    }

    public function sync(Site $site): void
    {
        if (! $this->appliesTo($site) || ! $this->configured()) {
            return;
        }

        $site->loadMissing('server');
        $ip = $site->server?->public_ip;
        if (! filled($ip)) {
            throw new RuntimeException('The server has no public IP to publish for '.$site->domain.'.');
        }

        $client = $this->client();
        $zoneId = (string) $this->settings->platformDnsCloudflareZoneId();
        $existing = $this->findARecord($client, $zoneId, $site);

        $payload = [
            'type' => 'A',
            'name' => $site->domain,
            'content' => $ip,
            'ttl' => 300,
            // Proxied orange-cloud breaks HTTP-01 Let's Encrypt challenges on the origin.
            'proxied' => false,
        ];

        if ($existing) {
            if ($existing['content'] === $ip && $existing['proxied'] === false) {
                $site->update(['platform_dns_record_id' => $existing['id']]);

                return;
            }

            $result = $client->updateRecord($zoneId, $existing['id'], $payload);
            $site->update(['platform_dns_record_id' => (string) ($result['id'] ?? $existing['id'])]);

            return;
        }

        $result = $client->createRecord($zoneId, $payload);
        $site->update(['platform_dns_record_id' => (string) ($result['id'] ?? '')]);
    }

    public function forget(Site $site): void
    {
        if (! $this->appliesTo($site) || ! $this->configured()) {
            return;
        }

        $client = $this->client();
        $zoneId = (string) $this->settings->platformDnsCloudflareZoneId();
        $recordId = $site->platform_dns_record_id;

        if (! filled($recordId)) {
            $existing = $this->findARecord($client, $zoneId, $site);
            $recordId = $existing['id'] ?? null;
        }

        if (! filled($recordId)) {
            return;
        }

        try {
            $client->deleteRecord($zoneId, (string) $recordId);
        } catch (DnsCredentialException $e) {
            // Already gone at Cloudflare — treat as success so site deletion is not blocked.
            if (! str_contains(strtolower($e->getMessage()), 'could not find')
                && ! str_contains(strtolower($e->getMessage()), 'not found')) {
                throw $e;
            }
        }

        if ($site->exists) {
            $site->update(['platform_dns_record_id' => null]);
        }
    }

    /**
     * Resolve and store the Cloudflare zone id for the configured platform staging apex.
     *
     * @throws DnsCredentialException
     * @throws ConnectionException
     */
    public function resolveAndStoreZoneId(string $token): string
    {
        $client = new CloudflareDns($token);
        $client->validateCredentials();

        $apex = $this->settings->stagingPlatformDomain();
        $zone = collect($client->zones())->first(
            fn (array $zone) => strtolower((string) $zone['name']) === $apex
        );

        if (! $zone) {
            throw new DnsCredentialException(
                "Cloudflare token works, but no zone named {$apex} is visible. Include that zone in the token's Zone Resources."
            );
        }

        $this->settings->put('platform_dns_cloudflare_zone_id', (string) $zone['id'], 'string', false);

        return (string) $zone['id'];
    }

    private function client(): CloudflareDns
    {
        return new CloudflareDns((string) $this->settings->platformDnsCloudflareToken());
    }

    /**
     * @return array{id: string, type: string, name: string, content: string, ttl: int, proxied: bool}|null
     */
    private function findARecord(CloudflareDns $client, string $zoneId, Site $site): ?array
    {
        $matches = $client->records($zoneId, [
            'type' => 'A',
            'name' => $site->domain,
        ]);

        return $matches[0] ?? null;
    }
}
