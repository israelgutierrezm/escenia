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
 * Sets the member's CUSTOM roles to exactly the given set (their system base
 * role is preserved). Idempotent: the caller sends the desired end state. The
 * actor may only attach roles whose combined permissions they hold.
 */
final class SyncMemberRolesAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function execute(User $actor, Tenant $tenant, User $member, array $roleNames): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $requested = [];
        $permissions = [];

        foreach (array_unique($roleNames) as $name) {
            if (AccessGuard::isSystemRole($name)) {
                throw new SystemRoleException('Change the base role via the membership-role endpoint, not here.');
            }

            $role = Role::query()->where('name', $name)->where('tenant_id', $tenant->getKey())->first();

            if ($role === null) {
                throw ValidationException::withMessages(['roles' => "Unknown role: {$name}."]);
            }

            $requested[] = $name;
            $permissions = array_merge($permissions, $role->permissions->pluck('name')->all());
        }

        AccessGuard::assertGrantable($actor, array_values(array_unique($permissions)));

        // Preserve the member's system base role; replace the custom set.
        $systemRoles = $member->getRoleNames()
            ->filter(fn (string $name): bool => AccessGuard::isSystemRole($name))
            ->values()
            ->all();

        $member->syncRoles(array_merge($systemRoles, $requested));

        $this->audit->log('rbac.member.roles_synced', actor: $actor, tenant: $tenant, auditable: $member, context: [
            'roles' => $requested,
        ]);
    }
}
