<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Support\Str;

/**
 * Deterministic, network-free identity provider for dev and tests. It trusts
 * the callback's `email`/`name` hints, which is exactly why it is never bound in
 * production (see EnterpriseServiceProvider). A `code` of `invalid` (or a
 * missing code) is rejected; anything else yields a stable external identity.
 * The connection's verified-domain rule still applies on top (ADR-034).
 */
final class FakeIdentityProvider implements IdentityProvider
{
    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        return 'https://sso.fake.local/authorize?'.http_build_query([
            'connection' => $connection->ulid,
            'redirect_uri' => $request->redirectUri,
            'state' => $request->state,
            'nonce' => $request->nonce,
            'code_challenge' => $request->codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        if ($callback->code === '' || $callback->code === 'invalid') {
            throw new SsoAuthenticationException;
        }

        $email = ($callback->hints['email'] ?? '') !== ''
            ? Str::lower($callback->hints['email'])
            : $callback->code.'@'.($connection->domain ?? 'sso.local');

        $name = ($callback->hints['name'] ?? '') !== ''
            ? $callback->hints['name']
            : Str::headline(Str::before($email, '@'));

        return new ExternalIdentity(
            subject: 'fake|'.sha1($connection->ulid.'|'.$email),
            email: $email,
            name: $name,
        );
    }
}
