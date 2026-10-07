<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Infrastructure\Http\OutboundUrlGuard;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a connection's OIDC client registration from its encrypted config.
 * Only the issuer and client credentials are mandatory: endpoints left out come
 * from the issuer's discovery document (OIDC Discovery §4) — fetched through
 * the egress guard, trusted only when it names the very same issuer, and cached.
 * An incomplete registration fails closed before any request reaches the IdP.
 */
final class OidcClientConfigResolver
{
    private const DISCOVERY_CACHE_SECONDS = 3600;

    private const ENDPOINTS = ['authorization_endpoint', 'token_endpoint', 'jwks_uri'];

    public function __construct(
        private readonly OutboundUrlGuard $guard,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws SsoAuthenticationException when the connection is not a usable OIDC registration
     */
    public function resolve(SsoConnection $connection): OidcClientConfig
    {
        if ($connection->provider !== SsoProvider::Oidc) {
            throw new SsoAuthenticationException;
        }

        $config = $connection->config;
        $read = static fn (string $key): string => isset($config[$key]) && is_string($config[$key]) ? trim($config[$key]) : '';

        $issuer = $read('issuer');
        $clientId = $read('client_id');
        $clientSecret = $read('client_secret');

        if ($issuer === '' || $clientId === '' || $clientSecret === '') {
            throw new SsoAuthenticationException;
        }

        $configured = [];
        foreach (self::ENDPOINTS as $field) {
            $configured[$field] = $read($field);
        }

        // Explicit endpoints win; discovery fills only what is missing.
        $endpoints = in_array('', $configured, true)
            ? array_merge($this->discover($issuer), array_filter($configured))
            : $configured;

        return new OidcClientConfig(
            issuer: $issuer,
            clientId: $clientId,
            clientSecret: $clientSecret,
            authorizationEndpoint: $endpoints['authorization_endpoint'],
            tokenEndpoint: $endpoints['token_endpoint'],
            jwksUri: $endpoints['jwks_uri'],
            scope: self::withOpenId($read('scope')),
        );
    }

    /**
     * @return array<string, string>
     */
    private function discover(string $issuer): array
    {
        $key = 'sso:oidc-discovery:'.hash('sha256', $issuer);
        $document = $this->cache->get($key);

        if (! is_array($document)) {
            $url = rtrim($issuer, '/').'/.well-known/openid-configuration';

            try {
                $document = Http::withOptions($this->guard->pinnedOptions($url))
                    ->acceptJson()->timeout(10)->get($url)->throw()->json();
            } catch (Throwable $e) {
                Log::notice('OIDC discovery failed.', ['issuer' => $issuer, 'error' => $e->getMessage()]);

                throw new SsoAuthenticationException;
            }

            $endpoints = self::endpointsFrom($document, $issuer);
            $this->cache->put($key, $document, self::DISCOVERY_CACHE_SECONDS);

            return $endpoints;
        }

        return self::endpointsFrom($document, $issuer);
    }

    /**
     * The endpoints of a discovery document — which must name exactly the
     * configured issuer (OIDC Discovery §4.3), or anyone could serve one.
     *
     * @return array<string, string>
     */
    private static function endpointsFrom(mixed $document, string $issuer): array
    {
        if (! is_array($document) || ($document['issuer'] ?? null) !== $issuer) {
            throw new SsoAuthenticationException;
        }

        $schemes = app()->isProduction() ? ['https'] : ['http', 'https'];
        $endpoints = [];

        foreach (self::ENDPOINTS as $field) {
            $url = $document[$field] ?? null;

            if (! is_string($url) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), $schemes, true)) {
                throw new SsoAuthenticationException;
            }

            $endpoints[$field] = $url;
        }

        return $endpoints;
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
