<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Abstracts the external identity provider behind an SSO connection. The domain
 * speaks only in terms of an authorization URL and a verified {@see
 * ExternalIdentity}; it never sees OIDC/SAML library types (ADR-008). Concrete
 * adapters validate signatures/assertions before returning an identity.
 */
interface IdentityProvider
{
    /**
     * Build the URL the browser is sent to in order to start authentication.
     */
    public function authorizationUrl(SsoConnection $connection, string $redirectUri, string $state): string;

    /**
     * Verify the provider's callback and return the authenticated identity.
     * Implementations MUST reject invalid/forged payloads by throwing
     * {@see SsoAuthenticationException}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(SsoConnection $connection, array $payload): ExternalIdentity;
}
