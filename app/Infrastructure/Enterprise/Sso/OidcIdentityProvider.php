<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * OIDC authorization-code adapter with PKCE (S256) and nonce. Redeems the code
 * at the token endpoint and returns an identity only after
 * {@see OidcIdTokenValidator} has verified the id_token's signature (JWKS) and
 * claims. Endpoints come from the connection's encrypted config.
 *
 * Not yet exercised against a live IdP (tests fake one with real signed tokens);
 * SAML connections are refused until a SAML adapter exists (see technical debt).
 */
final class OidcIdentityProvider implements IdentityProvider
{
    public function __construct(
        private readonly OidcIdTokenValidator $validator,
    ) {}

    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        $client = OidcClientConfig::fromConnection($connection);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client->clientId,
            'redirect_uri' => $request->redirectUri,
            'scope' => $client->scope,
            'state' => $request->state,
            'nonce' => $request->nonce,
            'code_challenge' => $request->codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $separator = str_contains($client->authorizationEndpoint, '?') ? '&' : '?';

        return $client->authorizationEndpoint.$separator.$query;
    }

    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        $client = OidcClientConfig::fromConnection($connection);

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->withoutRedirecting()
                ->post($client->tokenEndpoint, [
                    'grant_type' => 'authorization_code',
                    'code' => $callback->code,
                    'redirect_uri' => $callback->redirectUri,
                    'client_id' => $client->clientId,
                    'client_secret' => $client->clientSecret,
                    'code_verifier' => $callback->codeVerifier,
                ])
                ->throw();
        } catch (Throwable) {
            throw new SsoAuthenticationException;
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new SsoAuthenticationException;
        }

        $claims = $this->validator->validate($idToken, $client, $callback->nonce);

        $subject = is_string($claims['sub'] ?? null) ? $claims['sub'] : '';
        $email = is_string($claims['email'] ?? null) ? Str::lower(trim($claims['email'])) : '';

        if ($subject === '' || $email === '') {
            throw new SsoAuthenticationException;
        }

        $name = $claims['name'] ?? null;

        return new ExternalIdentity(
            subject: $subject,
            email: $email,
            // IdP-controlled free text: bounded to the column it lands in.
            name: is_string($name) && $name !== '' ? Str::limit($name, 255, '') : $email,
        );
    }
}
