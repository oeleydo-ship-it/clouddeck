<?php

namespace App\Services;

/**
 * Resolves public A/AAAA records for a hostname. Isolated so site DNS checks can
 * be stubbed in tests without hitting the network.
 */
class SiteDnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $hostname): array
    {
        $addresses = [];

        foreach (@dns_get_record($hostname, DNS_A) ?: [] as $record) {
            if (! empty($record['ip'])) {
                $addresses[] = $record['ip'];
            }
        }

        foreach (@dns_get_record($hostname, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ($addresses === []) {
            foreach (@gethostbynamel($hostname) ?: [] as $ip) {
                $addresses[] = $ip;
            }
        }

        return array_values(array_unique($addresses));
    }
}
