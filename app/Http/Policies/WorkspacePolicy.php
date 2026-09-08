<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Workspaces\Models\Workspace;

/**
 * Authorization for workspaces. Tenant isolation of the $workspace itself is
 * guaranteed upstream by the tenant global scope (a workspace from another
 * tenant is never resolved), so these checks focus on the permission the user
 * holds within the resolved tenant (Spatie team = tenant, ADR-011).
 */
class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkspacesView->value);
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $user->can(Permission::WorkspacesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WorkspacesCreate->value);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->can(Permission::WorkspacesUpdate->value);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->can(Permission::WorkspacesDelete->value);
    }
}
