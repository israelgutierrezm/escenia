<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * The real identity provider: hands each connection to the adapter for its
 * protocol (OIDC or SAML).
 */
final class RoutingIdentityProvider implements IdentityProvider
{
    public function __construct(
        private readonly OidcIdentityProvider $oidc,
        private readonly SamlIdentityProvider $saml,
    ) {}

    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        return $this->adapterFor($connection)->authorizationUrl($connection, $request);
    }

    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        return $this->adapterFor($connection)->verifyCallback($connection, $callback);
    }

    private function adapterFor(SsoConnection $connection): IdentityProvider
    {
        return match ($connection->provider) {
            SsoProvider::Oidc => $this->oidc,
            SsoProvider::Saml => $this->saml,
        };
    }
}
