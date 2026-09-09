<?php

declare(strict_types=1);

namespace App\Application\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\SystemRoleException;
use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a custom, tenant-scoped role with a chosen permission set. The name
 * may not collide with a system role, must be unique within the tenant, and the
 * actor may only grant permissions they themselves hold (anti-escalation).
 */
final class CreateRoleAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public function execute(User $actor, Tenant $tenant, string $name, array $permissions): Role
    {
        if (AccessGuard::isSystemRole($name)) {
            throw new SystemRoleException('That name is reserved for a system role.');
        }

        AccessGuard::assertGrantable($actor, $permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        if (Role::query()->where('name', $name)->where('tenant_id', $tenant->getKey())->exists()) {
            throw ValidationException::withMessages(['name' => "A role named {$name} already exists."]);
        }

        // Role::create() stamps the current team id (tenant); re-fetch through
        // the query builder to get a concretely-typed model back.
        Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);

        $role = Role::query()
            ->where('name', $name)
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();

        $this->audit->log('rbac.role.created', actor: $actor, tenant: $tenant, context: [
            'role' => $name,
            'permissions' => $permissions,
        ]);

        return $role;
    }
}
