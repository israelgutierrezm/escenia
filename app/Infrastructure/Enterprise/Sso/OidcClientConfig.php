<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

/**
 * A connection's resolved OIDC client registration (see
 * {@see OidcClientConfigResolver}): issuer, client credentials and endpoints.
 */
final class OidcClientConfig
{
    /** OIDC's default client authentication: credentials in the Authorization header. */
    public const AUTH_BASIC = 'client_secret_basic';

    /** Credentials in the token request body. */
    public const AUTH_POST = 'client_secret_post';

    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $jwksUri,
        public readonly string $scope,
        public readonly string $tokenEndpointAuthMethod = self::AUTH_BASIC,
        /** Where conformant IdPs return scope claims (email, name) that the ID token omits. */
        public readonly ?string $userinfoEndpoint = null,
    ) {}
}
