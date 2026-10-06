<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Abstracts the external identity provider behind an SSO connection. The domain
 * speaks only in terms of an authorization URL and a verified {@see
 * ExternalIdentity}; it never sees OIDC/SAML library types (ADR-008). Concrete
 * adapters validate signatures/assertions before returning an identity.
 *
 * Whether the identity may log in is NOT the adapter's call: the connection
 * only vouches for emails in its verified domain (ADR-034).
 */
interface IdentityProvider
{
    /**
     * Build the URL the browser is sent to in order to start authentication.
     *
     * @throws SsoAuthenticationException when the connection cannot be used
     */
    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string;

    /**
     * Redeem the callback and return the authenticated identity. Implementations
     * MUST reject invalid/forged payloads by throwing
     * {@see SsoAuthenticationException}.
     */
    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity;
}
