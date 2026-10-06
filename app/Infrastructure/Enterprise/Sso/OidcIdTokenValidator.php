<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Validates an OIDC id_token (OIDC Core §3.1.3.7): the signature against the
 * IdP's published keys, then `iss`, `aud`/`azp`, `exp`/`iat` and the `nonce` of
 * this login, plus `email_verified` when present. php-jwt stays behind this
 * adapter (ADR-008).
 */
final class OidcIdTokenValidator
{
    /**
     * Asymmetric algorithms only: `none`, and HS* keyed with a public key, are
     * the classic forgeries (algorithm confusion).
     */
    private const ALGORITHMS = ['RS256', 'RS384', 'RS512', 'ES256', 'ES384'];

    private const LEEWAY_SECONDS = 60;

    public function __construct(
        private readonly OidcJwksProvider $jwks,
    ) {}

    /**
     * @return array<mixed> the verified claims
     *
     * @throws SsoAuthenticationException
     */
    public function validate(string $idToken, OidcClientConfig $client, string $expectedNonce): array
    {
        $header = $this->header($idToken);
        $alg = $header['alg'] ?? null;

        if (! is_string($alg) || ! in_array($alg, self::ALGORITHMS, true)) {
            throw new SsoAuthenticationException;
        }

        $kid = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : null;
        $keys = $this->keysFor($this->jwks->keys($client->jwksUri), $alg);

        if ($kid !== null && ! isset($keys[$kid])) {
            $refreshed = $this->jwks->refresh($client->jwksUri);

            if ($refreshed !== null) {
                $keys = $this->keysFor($refreshed, $alg);
            }
        }

        $claims = $this->decode($idToken, $keys, $kid);

        if (! $this->claimsMatch($claims, $client, $expectedNonce)) {
            throw new SsoAuthenticationException;
        }

        return $claims;
    }

    /**
     * @return array<mixed>
     */
    private function header(string $idToken): array
    {
        $segment = strtr(explode('.', $idToken)[0], '-_', '+/');
        $json = base64_decode($segment.str_repeat('=', (4 - strlen($segment) % 4) % 4), true);
        $header = $json !== false ? json_decode($json, true) : null;

        if (! is_array($header)) {
            throw new SsoAuthenticationException;
        }

        return $header;
    }

    /**
     * The signing keys usable for `$alg`, each pinned to that algorithm. A JWK's
     * own `alg` wins; JWKs without one (e.g. Entra ID) get the token's alg only
     * within their family — RS* for RSA, the curve's ES* for EC — never HS*.
     *
     * @param  list<array<mixed>>  $jwks
     * @return array<string, Key>
     */
    private function keysFor(array $jwks, string $alg): array
    {
        $keys = [];

        foreach ($jwks as $index => $jwk) {
            if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
                continue;
            }

            $keyAlg = isset($jwk['alg']) && is_string($jwk['alg']) ? $jwk['alg'] : $this->familyAlgorithm($jwk, $alg);

            if ($keyAlg !== $alg) {
                continue;
            }

            try {
                $key = JWK::parseKey(['alg' => $keyAlg] + $jwk);
            } catch (Throwable) {
                continue; // malformed or unsupported key: skip it, never fail open
            }

            if ($key !== null) {
                $keys[isset($jwk['kid']) && is_string($jwk['kid']) ? $jwk['kid'] : (string) $index] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<mixed>  $jwk
     */
    private function familyAlgorithm(array $jwk, string $alg): ?string
    {
        return match ($jwk['kty'] ?? null) {
            'RSA' => str_starts_with($alg, 'RS') ? $alg : null,
            'EC' => match ($jwk['crv'] ?? null) {
                'P-256' => 'ES256',
                'P-384' => 'ES384',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @param  array<string, Key>  $keys
     * @return array<mixed>
     */
    private function decode(string $idToken, array $keys, ?string $kid): array
    {
        // Without a `kid`, only a single unambiguous key may be tried.
        $keyOrKeys = match (true) {
            $keys === [] => throw new SsoAuthenticationException,
            $kid !== null => $keys,
            count($keys) === 1 => array_values($keys)[0],
            default => throw new SsoAuthenticationException,
        };

        $leeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            $payload = JWT::decode($idToken, $keyOrKeys);
        } catch (Throwable) {
            throw new SsoAuthenticationException;
        } finally {
            JWT::$leeway = $leeway;
        }

        $claims = json_decode((string) json_encode($payload), true);

        if (! is_array($claims)) {
            throw new SsoAuthenticationException;
        }

        return $claims;
    }

    /**
     * php-jwt has already checked the signature and any `exp`/`nbf`/`iat`; this
     * checks the token is meant for this client and this login.
     *
     * @param  array<mixed>  $claims
     */
    private function claimsMatch(array $claims, OidcClientConfig $client, string $expectedNonce): bool
    {
        $aud = $claims['aud'] ?? null;
        $audiences = is_string($aud) ? [$aud] : (is_array($aud) ? $aud : []);
        $azp = $claims['azp'] ?? null;
        $nonce = $claims['nonce'] ?? null;

        return ($claims['iss'] ?? null) === $client->issuer
            && in_array($client->clientId, $audiences, true)
            // Several audiences: the authorized party must be this client.
            && (count($audiences) === 1 || $azp === $client->clientId)
            && ($azp === null || $azp === $client->clientId)
            // php-jwt only checks these when present; OIDC requires both.
            && is_numeric($claims['exp'] ?? null)
            && is_numeric($claims['iat'] ?? null)
            && is_string($nonce) && hash_equals($expectedNonce, $nonce)
            && (! array_key_exists('email_verified', $claims)
                || filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true);
    }
}
