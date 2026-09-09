<?php

declare(strict_types=1);

namespace App\Application\AccessControl\Actions;

use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replaces the permission set of a custom role. System roles are immutable; the
 * actor may only assign permissions they themselves hold.
 */
final class UpdateRoleAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public function execute(User $actor, Tenant $tenant, Role $role, array $permissions): Role
    {
        AccessGuard::assertCustomRole($role);
        AccessGuard::assertGrantable($actor, $permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $role->syncPermissions($permissions);

        $this->audit->log('rbac.role.updated', actor: $actor, tenant: $tenant, context: [
            'role' => $role->name,
            'permissions' => $permissions,
        ]);

        return $role;
    }
}
