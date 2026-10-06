<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * The OIDC client registration read from a connection's encrypted config. An
 * incomplete registration fails closed before any request reaches the IdP.
 */
final class OidcClientConfig
{
    private function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $jwksUri,
        public readonly string $scope,
    ) {}

    /**
     * @throws SsoAuthenticationException when the connection is not a complete OIDC registration
     */
    public static function fromConnection(SsoConnection $connection): self
    {
        if ($connection->provider !== SsoProvider::Oidc) {
            throw new SsoAuthenticationException;
        }

        $config = $connection->config;
        $read = static fn (string $key): string => isset($config[$key]) && is_string($config[$key]) ? trim($config[$key]) : '';

        $client = new self(
            issuer: $read('issuer'),
            clientId: $read('client_id'),
            clientSecret: $read('client_secret'),
            authorizationEndpoint: $read('authorization_endpoint'),
            tokenEndpoint: $read('token_endpoint'),
            jwksUri: $read('jwks_uri'),
            scope: self::withOpenId($read('scope')),
        );

        foreach ([$client->issuer, $client->clientId, $client->clientSecret, $client->authorizationEndpoint, $client->tokenEndpoint, $client->jwksUri] as $value) {
            if ($value === '') {
                throw new SsoAuthenticationException;
            }
        }

        return $client;
    }

    /**
     * Without the `openid` scope the IdP returns no id_token at all.
     */
    private static function withOpenId(string $scope): string
    {
        if ($scope === '') {
            return 'openid email profile';
        }

        return in_array('openid', explode(' ', $scope), true) ? $scope : 'openid '.$scope;
    }
}
