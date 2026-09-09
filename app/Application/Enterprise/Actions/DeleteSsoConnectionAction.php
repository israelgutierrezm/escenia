<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;

/**
 * Removes an SSO connection. Audited before deletion (disabling a login path is
 * security-relevant). Existing user sessions are unaffected; only future SSO
 * logins through this connection stop working.
 */
final class DeleteSsoConnectionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, SsoConnection $connection): void
    {
        $this->audit->log('enterprise.sso.connection_deleted', actor: $actor, auditable: $connection, context: [
            'provider' => $connection->provider->value,
            'domain' => $connection->domain,
        ]);

        $connection->delete();
    }
}
