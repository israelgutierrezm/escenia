<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Enums\DomainStatus;
use App\Domain\Enterprise\Exceptions\DomainVerificationFailedException;
use App\Domain\Enterprise\Models\CustomDomain;
use App\Domain\Identity\Models\User;

/**
 * Runs the ownership challenge for a custom domain through the configured
 * {@see DomainVerifier}. On success the domain becomes `active`; on failure it
 * is marked `failed` and a 422 is surfaced (the tenant can re-verify).
 */
final class VerifyCustomDomainAction
{
    public function __construct(
        private readonly DomainVerifier $verifier,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, CustomDomain $domain): CustomDomain
    {
        $result = $this->verifier->verify($domain);

        if (! $result->verified) {
            $domain->update(['status' => DomainStatus::Failed]);

            $this->audit->log('enterprise.domain.verification_failed', actor: $actor, auditable: $domain, context: [
                'hostname' => $domain->hostname,
                'detail' => $result->detail,
            ]);

            throw new DomainVerificationFailedException($result->detail ?? 'Domain ownership could not be verified.');
        }

        $domain->update([
            'status' => DomainStatus::Active,
            'verified_at' => now(),
        ]);

        $this->audit->log('enterprise.domain.verified', actor: $actor, auditable: $domain, context: [
            'hostname' => $domain->hostname,
        ]);

        return $domain->refresh();
    }
}
