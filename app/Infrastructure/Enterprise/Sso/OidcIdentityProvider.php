<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Support\Facades\Http;

/**
 * OIDC authorization-code adapter (stub). Builds the authorization request from
 * the connection config and exchanges the code at the token endpoint.
 *
 * NOT integration-tested and NOT production-ready: it reads the id_token claims
 * without validating the JWT signature against the provider's JWKS. A real
 * deployment MUST verify the signature, `iss`, `aud`, `exp` and `nonce` before
 * trusting the identity (see technical debt). The default provider is the fake.
 */
final class OidcIdentityProvider implements IdentityProvider
{
    public function authorizationUrl(SsoConnection $connection, string $redirectUri, string $state): string
    {
        $config = $connection->config;

        $endpoint = (string) ($config['authorization_endpoint'] ?? '');

        if ($endpoint === '') {
            throw new SsoAuthenticationException('SSO connection is not configured.');
        }

        return $endpoint.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) ($config['client_id'] ?? ''),
            'redirect_uri' => $redirectUri,
            'scope' => (string) ($config['scope'] ?? 'openid email profile'),
            'state' => $state,
        ]);
    }

    public function verifyCallback(SsoConnection $connection, array $payload): ExternalIdentity
    {
        $config = $connection->config;
        $code = isset($payload['code']) ? (string) $payload['code'] : '';
        $tokenEndpoint = (string) ($config['token_endpoint'] ?? '');

        if ($code === '' || $tokenEndpoint === '') {
            throw new SsoAuthenticationException;
        }

        try {
            $response = Http::asForm()->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => (string) ($config['client_id'] ?? ''),
                'client_secret' => (string) ($config['client_secret'] ?? ''),
                'redirect_uri' => (string) ($payload['redirect_uri'] ?? ''),
            ])->throw();
        } catch (\Throwable) {
            throw new SsoAuthenticationException;
        }

        $idToken = (string) $response->json('id_token', '');
        $claims = $this->decodeClaims($idToken);

        $email = isset($claims['email']) ? (string) $claims['email'] : '';
        $subject = isset($claims['sub']) ? (string) $claims['sub'] : '';

        if ($email === '' || $subject === '') {
            throw new SsoAuthenticationException;
        }

        return new ExternalIdentity(
            subject: $subject,
            email: strtolower($email),
            name: isset($claims['name']) ? (string) $claims['name'] : $email,
        );
    }

    /**
     * Decode the (unverified) claims segment of a JWT. Signature validation is
     * intentionally out of scope for this stub — see the class docblock.
     *
     * @return array<string, mixed>
     */
    private function decodeClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new SsoAuthenticationException;
        }

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($decoded === false) {
            throw new SsoAuthenticationException;
        }

        /** @var array<string, mixed>|null $claims */
        $claims = json_decode($decoded, true);

        if (! is_array($claims)) {
            throw new SsoAuthenticationException;
        }

        return $claims;
    }
}
