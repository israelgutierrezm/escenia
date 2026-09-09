<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Support\Str;

/**
 * Deterministic, network-free identity provider for dev and tests. The callback
 * payload is a small body `{code, email?, name?}`: a `code` of `invalid` (or a
 * missing code) is rejected; anything else yields a stable external identity.
 * No real IdP is contacted.
 */
final class FakeIdentityProvider implements IdentityProvider
{
    public function authorizationUrl(SsoConnection $connection, string $redirectUri, string $state): string
    {
        return 'https://sso.fake.local/authorize?'.http_build_query([
            'connection' => $connection->ulid,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function verifyCallback(SsoConnection $connection, array $payload): ExternalIdentity
    {
        $code = isset($payload['code']) ? (string) $payload['code'] : '';

        if ($code === '' || $code === 'invalid') {
            throw new SsoAuthenticationException;
        }

        $email = isset($payload['email']) && is_string($payload['email']) && $payload['email'] !== ''
            ? Str::lower((string) $payload['email'])
            : $code.'@'.($connection->domain ?? 'sso.local');

        $name = isset($payload['name']) && is_string($payload['name']) && $payload['name'] !== ''
            ? (string) $payload['name']
            : Str::headline(Str::before($email, '@'));

        return new ExternalIdentity(
            subject: 'fake|'.sha1($connection->ulid.'|'.$email),
            email: $email,
            name: $name,
        );
    }
}
