<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Guards server-side requests to URLs a tenant configured (e.g. its IdP's
 * endpoints) against SSRF. The host must resolve only to public addresses —
 * no private, loopback, link-local (cloud metadata), CGNAT or reserved ranges —
 * and the request is pinned to the address that was checked, so DNS cannot be
 * rebound between the check and the connection. Redirects are never followed.
 *
 * `allowPrivateNetworks` exists for local development against an IdP on
 * localhost; it is off unless configured.
 */
final class OutboundUrlGuard
{
    public function __construct(
        private readonly HostResolver $resolver,
        private readonly bool $allowPrivateNetworks = false,
    ) {}

    /**
     * Guzzle options for a request to `$url` (pass to `Http::withOptions()`).
     *
     * @return array<string, mixed>
     *
     * @throws UnsafeOutboundUrlException
     */
    public function pinnedOptions(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeOutboundUrlException('Unsupported outbound URL.');
        }

        $literal = trim($host, '[]');
        $isLiteral = filter_var($literal, FILTER_VALIDATE_IP) !== false;
        $addresses = $isLiteral ? [$literal] : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new UnsafeOutboundUrlException("Host [{$host}] does not resolve.");
        }

        if (! $this->allowPrivateNetworks) {
            foreach ($addresses as $address) {
                if (! self::isPublic($address)) {
                    throw new UnsafeOutboundUrlException("Host [{$host}] resolves to a non-public address.");
                }
            }
        }

        $options = ['allow_redirects' => false];

        if (! $isLiteral) {
            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            $pinned = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]];
        }

        return $options;
    }

    public static function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        // IPv6 with an embedded IPv4 (::ffff:10.0.0.1) could smuggle a private v4 address.
        return ! (str_contains($address, ':') && str_contains($address, '.'));
    }
}
