<?php

declare(strict_types=1);

namespace App\Application\AccessControl\Actions;

use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role;

/**
 * Deletes a custom role. System roles cannot be deleted. Spatie removes the
 * role's assignments and permission links as part of the delete.
 */
final class DeleteRoleAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, Tenant $tenant, Role $role): void
    {
        AccessGuard::assertCustomRole($role);

        $name = (string) $role->name;
        $role->delete();

        $this->audit->log('rbac.role.deleted', actor: $actor, tenant: $tenant, context: [
            'role' => $name,
        ]);
    }
}
