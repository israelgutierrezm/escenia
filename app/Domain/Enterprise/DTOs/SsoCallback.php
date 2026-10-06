<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

/**
 * A provider callback paired with the server-side secrets of the attempt that
 * started it: the redirect URI and nonce it was issued with and the PKCE
 * verifier. `hints` are untrusted extras only the dev/test fake reads.
 */
final class SsoCallback
{
    /**
     * @param  array<string, string>  $hints
     */
    public function __construct(
        public readonly string $code,
        public readonly string $redirectUri,
        public readonly string $nonce,
        public readonly string $codeVerifier,
        public readonly array $hints = [],
    ) {}
}
