<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Infrastructure\Http\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OIDC authorization-code adapter with PKCE (S256) and nonce. Redeems the code
 * at the token endpoint — through the egress guard, the endpoint being
 * tenant-configured — and returns an identity only after
 * {@see OidcIdTokenValidator} has verified the id_token's signature (JWKS) and
 * claims. Endpoints come from the connection's config or the issuer's
 * discovery document ({@see OidcClientConfigResolver}).
 *
 * Exercised against a fake IdP with genuinely signed tokens; a live-IdP run is
 * documented in docs/architecture/sso-real-idp.md.
 */
final class OidcIdentityProvider implements IdentityProvider
{
    public function __construct(
        private readonly OidcClientConfigResolver $clients,
        private readonly OidcIdTokenValidator $validator,
        private readonly OutboundUrlGuard $guard,
    ) {}

    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        $client = $this->clients->resolve($connection);

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
        $client = $this->clients->resolve($connection);

        $form = [
            'grant_type' => 'authorization_code',
            'code' => $callback->code,
            'redirect_uri' => $callback->redirectUri,
            'code_verifier' => $callback->codeVerifier,
        ];

        try {
            $request = Http::withOptions($this->guard->pinnedOptions($client->tokenEndpoint))
                ->asForm()->acceptJson()->timeout(10);

            if ($client->tokenEndpointAuthMethod === OidcClientConfig::AUTH_POST) {
                $form += ['client_id' => $client->clientId, 'client_secret' => $client->clientSecret];
            } else {
                // RFC 6749 §2.3.1: form-urlencode the credentials before Basic encoding.
                $request = $request->withBasicAuth(urlencode($client->clientId), urlencode($client->clientSecret));
            }

            $response = $request->post($client->tokenEndpoint, $form)->throw();
        } catch (Throwable $e) {
            Log::notice('OIDC code exchange failed.', ['connection' => $connection->ulid, 'error' => $e->getMessage()]);

            throw new SsoAuthenticationException;
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new SsoAuthenticationException;
        }

        $claims = $this->validator->validate($idToken, $client, $callback->nonce);
        $subject = is_string($claims['sub'] ?? null) ? $claims['sub'] : '';

        // Conformant IdPs may return scope claims (email, name) only from UserInfo.
        if ($subject !== '' && ! is_string($claims['email'] ?? null)) {
            $claims = array_merge($claims, $this->userinfo($connection, $client, $response->json('access_token'), $subject));
        }

        $email = is_string($claims['email'] ?? null) ? Str::lower(trim($claims['email'])) : '';

        if ($subject === '' || $email === '') {
            Log::notice('OIDC login without a subject or an email.', ['connection' => $connection->ulid]);

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

    /**
     * Email/name from UserInfo (OIDC Core §5.3) for the subject the ID token
     * proved: a response for another `sub` is ignored (§5.3.2), and an email the
     * IdP marks unverified is too — nothing is taken but these three claims.
     *
     * @return array<string, mixed>
     */
    private function userinfo(SsoConnection $connection, OidcClientConfig $client, mixed $accessToken, string $subject): array
    {
        if ($client->userinfoEndpoint === null || ! is_string($accessToken) || $accessToken === '') {
            return [];
        }

        try {
            $claims = Http::withOptions($this->guard->pinnedOptions($client->userinfoEndpoint))
                ->withToken($accessToken)->acceptJson()->timeout(10)
                ->get($client->userinfoEndpoint)->throw()->json();
        } catch (Throwable $e) {
            Log::notice('OIDC UserInfo request failed.', ['connection' => $connection->ulid, 'error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($claims) || ($claims['sub'] ?? null) !== $subject) {
            Log::notice('OIDC UserInfo answered for another subject.', ['connection' => $connection->ulid]);

            return [];
        }

        if (array_key_exists('email_verified', $claims)
            && filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== true) {
            return [];
        }

        $taken = [];

        foreach (['email', 'email_verified', 'name'] as $claim) {
            if (array_key_exists($claim, $claims)) {
                $taken[$claim] = $claims[$claim];
            }
        }

        return $taken;
    }
}
