<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Jobs;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-runs the DNS challenge of a verified SSO domain (ADR-035). Domains change
 * hands: when the TXT record disappears, the connection must stop vouching for
 * that domain. A failure opens a grace period (`enterprise.sso_domain_grace_hours`,
 * so a DNS blip does not lock everyone out); still failing after it, the
 * verification is revoked and audited. Loaded unscoped (no tenant context on
 * the worker).
 */
class ReverifySsoDomainJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $connectionId,
    ) {}

    public function handle(DomainVerifier $verifier, AuditLogger $audit): void
    {
        $connection = SsoConnection::query()->withoutGlobalScopes()->find($this->connectionId);
        $challenge = $connection?->dnsChallenge();

        if ($connection === null || $challenge === null || ! $connection->hasVerifiedDomain()) {
            return;
        }

        $result = $verifier->verify($challenge);
        $now = now();

        if ($result->verified) {
            $connection->forceFill(['domain_checked_at' => $now, 'domain_check_failed_at' => null])->save();

            return;
        }

        $failingSince = $connection->domain_check_failed_at ?? $now;
        $graceEnds = $failingSince->copy()->addHours((int) config('enterprise.sso_domain_grace_hours', 72));

        if ($now->greaterThanOrEqualTo($graceEnds)) {
            $connection->forceFill([
                'domain_verified_at' => null,
                'domain_checked_at' => $now,
                'domain_check_failed_at' => null,
            ])->save();

            $audit->log('enterprise.sso.domain_unverified', tenant: $connection->tenant, auditable: $connection, context: [
                'domain' => $connection->domain,
                'failing_since' => $failingSince->toIso8601String(),
                'detail' => $result->detail,
            ]);

            return;
        }

        if ($connection->domain_check_failed_at === null) {
            $audit->log('enterprise.sso.domain_check_failed', tenant: $connection->tenant, auditable: $connection, context: [
                'domain' => $connection->domain,
                'revoked_at' => $graceEnds->toIso8601String(),
                'detail' => $result->detail,
            ]);
        }

        $connection->forceFill(['domain_checked_at' => $now, 'domain_check_failed_at' => $failingSince])->save();
    }
}
