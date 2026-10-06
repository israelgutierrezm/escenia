<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Application\Enterprise\DTOs\SsoLoginAttempt;
use App\Application\Enterprise\DTOs\SsoLoginStart;
use App\Application\Enterprise\SsoLoginAttempts;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Starts an SSO login: issues a fresh attempt (state, nonce, PKCE verifier,
 * browser binding), asks the provider for the authorization URL, and only then
 * remembers the attempt — a provider that refuses the connection leaves nothing
 * behind.
 */
final class StartSsoLoginAction
{
    public function __construct(
        private readonly IdentityProvider $identityProvider,
        private readonly SsoLoginAttempts $attempts,
    ) {}

    public function execute(SsoConnection $connection, string $redirectUri): SsoLoginStart
    {
        $attempt = SsoLoginAttempt::issue($connection->id, $redirectUri);

        $authorizationUrl = $this->identityProvider->authorizationUrl($connection, new SsoAuthorizationRequest(
            redirectUri: $redirectUri,
            state: $attempt->state,
            nonce: $attempt->nonce,
            codeChallenge: $attempt->codeChallenge(),
        ));

        $this->attempts->remember($attempt);

        return new SsoLoginStart($authorizationUrl, $attempt->state, $attempt->binding);
    }
}
