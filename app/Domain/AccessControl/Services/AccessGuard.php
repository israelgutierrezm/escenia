<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Services;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\AccessControl\Exceptions\PrivilegeEscalationException;
use App\Domain\AccessControl\Exceptions\SystemRoleException;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Enums\TenantRole;
use Spatie\Permission\Models\Role;

/**
 * The security guard for the RBAC management surface. Two invariants:
 *
 *  1. System roles (owner/admin/member) are managed through membership, never
 *     as custom roles.
 *  2. No one can grant access they do not themselves hold (anti-escalation),
 *     and only an owner may touch the owner tier.
 *
 * The Spatie permission team must already be bound to the acting tenant (the
 * ResolveTenant middleware does this) so the actor's effective permissions
 * resolve within the right tenant.
 */
final class AccessGuard
{
    public static function isSystemRole(string $name): bool
    {
        return in_array($name, TenantRole::values(), true);
    }

    public static function assertCustomRole(Role $role): void
    {
        if (self::isSystemRole((string) $role->name)) {
            throw new SystemRoleException;
        }
    }

    /**
     * The actor may only assign/attach permissions that are a subset of their
     * own effective permissions.
     *
     * @param  list<string>  $permissions
     */
    public static function assertGrantable(User $actor, array $permissions): void
    {
        $own = $actor->getAllPermissions()->pluck('name')->all();
        $exceeding = array_values(array_diff($permissions, $own));

        if ($exceeding !== []) {
            throw new PrivilegeEscalationException(
                'You cannot grant permissions you do not hold: '.implode(', ', $exceeding).'.'
            );
        }
    }

    /**
     * Touching the owner tier (assigning/removing the owner role) requires the
     * actor to be an owner — i.e. to hold tenant.manage.
     */
    public static function assertMayManageOwner(User $actor): void
    {
        if ($actor->can(Permission::TenantManage->value) !== true) {
            throw new PrivilegeEscalationException('Only an owner can manage the owner role.');
        }
    }
}
