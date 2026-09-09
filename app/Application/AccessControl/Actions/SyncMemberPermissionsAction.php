<?php

declare(strict_types=1);

namespace App\Application\AccessControl\Actions;

use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets a member's DIRECT permission grants to exactly the given set (on top of
 * whatever their roles already grant). Idempotent. The actor may only grant
 * permissions they themselves hold (anti-escalation); revoking a grant carries
 * no such risk.
 */
final class SyncMemberPermissionsAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public function execute(User $actor, Tenant $tenant, User $member, array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));

        AccessGuard::assertGrantable($actor, $permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $member->syncPermissions($permissions);

        $this->audit->log('rbac.member.permissions_synced', actor: $actor, tenant: $tenant, auditable: $member, context: [
            'permissions' => $permissions,
        ]);
    }
}
