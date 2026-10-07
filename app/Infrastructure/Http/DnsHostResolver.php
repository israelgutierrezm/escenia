<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * System DNS resolution: A + AAAA records, falling back to the resolver library
 * (which also honours the hosts file) when DNS returns nothing.
 */
final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip)) {
                $addresses[] = $ip;
            }
        }

        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }
}
