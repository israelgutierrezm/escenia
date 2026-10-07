<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Exceptions\DomainVerificationFailedException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Proves the tenant controls the SSO connection's email domain by running its
 * DNS TXT challenge through the configured {@see DomainVerifier}. Only a
 * verified domain lets the connection vouch for logins (ADR-034), and only one
 * organization may hold a domain at a time (ADR-035) — checked under a lock so
 * two tenants cannot verify it at once. A failed check surfaces a 422 and the
 * tenant can retry; a verified domain is then re-checked daily.
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

        $lock = Cache::lock('sso-domain:'.$connection->domain, 10);
        $lock->block(5);

        try {
            if ($this->heldByAnotherTenant($connection)) {
                $this->failed($actor, $connection, 'Domain already verified by another organization.');
            }

            $result = $this->verifier->verify($challenge);

            if (! $result->verified) {
                $this->failed($actor, $connection, $result->detail ?? 'Domain ownership could not be verified.');
            }

            $connection->forceFill([
                'domain_verified_at' => now(),
                'domain_checked_at' => now(),
                'domain_check_failed_at' => null,
            ])->save();
        } finally {
            $lock->release();
        }

        $this->audit->log('enterprise.sso.domain_verified', actor: $actor, auditable: $connection, context: [
            'domain' => $connection->domain,
        ]);

        return $connection->refresh();
    }

    private function heldByAnotherTenant(SsoConnection $connection): bool
    {
        return SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('domain', $connection->domain)
            ->whereNotNull('domain_verified_at')
            ->where('tenant_id', '!=', $connection->tenant_id)
            ->exists();
    }

    private function failed(User $actor, SsoConnection $connection, string $detail): never
    {
        $this->audit->log('enterprise.sso.domain_verification_failed', actor: $actor, auditable: $connection, context: [
            'domain' => $connection->domain,
            'detail' => $detail,
        ]);

        throw new DomainVerificationFailedException($detail);
    }
}
