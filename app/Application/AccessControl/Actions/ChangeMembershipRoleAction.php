<?php

declare(strict_types=1);

namespace App\Application\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\PrivilegeEscalationException;
use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use Spatie\Permission\PermissionRegistrar;

/**
 * Changes a member's base tier (owner/admin/member). This is the authoritative
 * membership role, mirrored to the member's Spatie system-role assignment; any
 * custom roles and direct permissions the member has are left untouched.
 *
 * Guards: touching the owner tier requires the actor to be an owner, and the
 * tenant must always keep at least one owner (no self-lockout).
 */
final class ChangeMembershipRoleAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, Tenant $tenant, User $member, TenantRole $newRole): void
    {
        $membership = $member->tenantMembershipFor($tenant);

        if ($membership === null) {
            throw new PrivilegeEscalationException('That user is not a member of this tenant.');
        }

        $current = $membership->role;

        if ($newRole === $current) {
            return;
        }

        if ($newRole === TenantRole::Owner || $current === TenantRole::Owner) {
            AccessGuard::assertMayManageOwner($actor);
        }

        if ($current === TenantRole::Owner && $newRole !== TenantRole::Owner) {
            $owners = TenantMembership::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('role', TenantRole::Owner->value)
                ->count();

            if ($owners <= 1) {
                throw new PrivilegeEscalationException('The tenant must keep at least one owner.');
            }
        }

        $membership->update(['role' => $newRole]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        foreach (TenantRole::values() as $systemRole) {
            if ($member->hasRole($systemRole)) {
                $member->removeRole($systemRole);
            }
        }
        $member->assignRole($newRole->value);

        $this->audit->log('rbac.member.role_changed', actor: $actor, tenant: $tenant, auditable: $member, context: [
            'from' => $current->value,
            'to' => $newRole->value,
        ]);
    }
}
