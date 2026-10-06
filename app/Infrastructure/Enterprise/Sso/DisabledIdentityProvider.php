<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Fail-closed provider, bound when no real adapter is configured where the fake
 * is not allowed (production). Unconfigured SSO must mean "no SSO logins", never
 * "trust whatever the callback says".
 */
final class DisabledIdentityProvider implements IdentityProvider
{
    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        throw new SsoAuthenticationException;
    }

    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        throw new SsoAuthenticationException;
    }
}
