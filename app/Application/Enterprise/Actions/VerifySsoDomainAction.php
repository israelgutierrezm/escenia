<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Exceptions\DomainVerificationFailedException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;

/**
 * Proves the tenant controls the SSO connection's email domain by running its
 * DNS TXT challenge through the configured {@see DomainVerifier}. Only a
 * verified domain lets the connection vouch for logins (ADR-034); a failed
 * check surfaces a 422 and the tenant can retry.
 */
final class VerifySsoDomainAction
{
    public function __construct(
        private readonly DomainVerifier $verifier,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, SsoConnection $connection): SsoConnection
    {
        $challenge = $connection->dnsChallenge()
            ?? throw new DomainVerificationFailedException('The SSO connection has no domain to verify.');

        $result = $this->verifier->verify($challenge);

        if (! $result->verified) {
            $this->audit->log('enterprise.sso.domain_verification_failed', actor: $actor, auditable: $connection, context: [
                'domain' => $connection->domain,
                'detail' => $result->detail,
            ]);

            throw new DomainVerificationFailedException($result->detail ?? 'Domain ownership could not be verified.');
        }

        $connection->forceFill(['domain_verified_at' => now()])->save();

        $this->audit->log('enterprise.sso.domain_verified', actor: $actor, auditable: $connection, context: [
            'domain' => $connection->domain,
        ]);

        return $connection->refresh();
    }
}
