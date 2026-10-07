<?php

declare(strict_types=1);

namespace App\Application\Enterprise;

use App\Application\Enterprise\DTOs\SsoLoginAttempt;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Server-side memory of in-flight SSO logins, keyed by `state`. An attempt
 * expires after ten minutes and can be claimed exactly once, only by the
 * connection it was issued for and only with the browser binding it was issued
 * with — so a state planted in another browser (login CSRF, code injection) or
 * replayed is worthless.
 */
final class SsoLoginAttempts
{
    public const TTL_SECONDS = 600;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function remember(SsoLoginAttempt $attempt): void
    {
        $this->cache->put($this->key($attempt->state), [
            'connection_id' => $attempt->connectionId,
            'redirect_uri' => $attempt->redirectUri,
            'nonce' => $attempt->nonce,
            'code_verifier' => $attempt->codeVerifier,
            'binding_hash' => hash('sha256', $attempt->binding),
        ], self::TTL_SECONDS);
    }

    /**
     * Returns null — without consuming the attempt — when the state is unknown
     * or expired, was issued for another connection, or the binding does not
     * match. Otherwise consumes it atomically: only the first claim wins.
     */
    public function claim(string $state, SsoConnection $connection, ?string $binding): ?SsoLoginAttempt
    {
        $key = $this->key($state);
        $stored = $this->cache->get($key);

        if (! is_array($stored)
            || $binding === null
            || $binding === ''
            || ($stored['connection_id'] ?? null) !== $connection->id
            || ! hash_equals((string) ($stored['binding_hash'] ?? ''), hash('sha256', $binding))) {
            return null;
        }

        if (! $this->cache->add($key.':claimed', true, self::TTL_SECONDS)) {
            return null;
        }

        $this->cache->forget($key);

        return new SsoLoginAttempt(
            state: $state,
            connectionId: $connection->id,
            redirectUri: (string) ($stored['redirect_uri'] ?? ''),
            nonce: (string) ($stored['nonce'] ?? ''),
            codeVerifier: (string) ($stored['code_verifier'] ?? ''),
            binding: $binding,
        );
    }

    /**
     * The URL the login asked to return to, without consuming the attempt — so
     * a SAML ACS can send the browser back even when the login fails. Null for
     * an unknown or expired state.
     */
    public function returnUrl(string $state): ?string
    {
        $stored = $this->cache->get($this->key($state));
        $url = is_array($stored) ? ($stored['redirect_uri'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function key(string $state): string
    {
        return 'sso:attempt:'.hash('sha256', $state);
    }
}
