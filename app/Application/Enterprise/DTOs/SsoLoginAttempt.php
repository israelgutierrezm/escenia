<?php

declare(strict_types=1);

namespace App\Application\Enterprise\DTOs;

use Illuminate\Support\Str;

/**
 * One in-flight SSO login. `state` goes to the IdP and back; `binding` goes to
 * the browser that started the login (HttpOnly cookie); the nonce and PKCE
 * verifier never leave the server.
 */
final class SsoLoginAttempt
{
    public function __construct(
        public readonly string $state,
        public readonly int $connectionId,
        public readonly string $redirectUri,
        public readonly string $nonce,
        public readonly string $codeVerifier,
        public readonly string $binding,
    ) {}

    public static function issue(int $connectionId, string $redirectUri): self
    {
        return new self(
            state: Str::random(40),
            connectionId: $connectionId,
            redirectUri: $redirectUri,
            nonce: Str::random(40),
            codeVerifier: Str::random(64),
            binding: Str::random(40),
        );
    }

    /**
     * The PKCE S256 challenge for the verifier (RFC 7636 §4.2).
     */
    public function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
    }
}
