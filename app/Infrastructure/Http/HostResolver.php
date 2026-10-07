<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Resolves a hostname to the addresses a connection would use. Behind a
 * contract so the outbound-URL guard is testable without live DNS.
 */
interface HostResolver
{
    /**
     * @return list<string> IPv4/IPv6 addresses; empty when the host does not resolve
     */
    public function resolve(string $host): array;
}
