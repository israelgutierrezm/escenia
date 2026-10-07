<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

/**
 * A connection's resolved OIDC client registration (see
 * {@see OidcClientConfigResolver}): issuer, client credentials and endpoints.
 */
final class OidcClientConfig
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $jwksUri,
        public readonly string $scope,
    ) {}
}
