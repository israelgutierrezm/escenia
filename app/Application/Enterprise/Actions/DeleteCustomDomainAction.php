<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\CustomDomain;
use App\Domain\Identity\Models\User;

/**
 * Removes a custom domain. Deleting an enterprise routing record is
 * security-relevant, so it is audited before the row is dropped.
 */
final class DeleteCustomDomainAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, CustomDomain $domain): void
    {
        $this->audit->log('enterprise.domain.deleted', actor: $actor, auditable: $domain, context: [
            'hostname' => $domain->hostname,
        ]);

        $domain->delete();
    }
}
