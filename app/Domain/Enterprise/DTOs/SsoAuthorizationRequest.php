<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

/**
 * What the IdP needs to start one login: where to send the browser back, the
 * `state` and `nonce` bound to this attempt, and the PKCE (S256) challenge.
 */
final class SsoAuthorizationRequest
{
    public function __construct(
        public readonly string $redirectUri,
        public readonly string $state,
        public readonly string $nonce,
        public readonly string $codeChallenge,
    ) {}
}
