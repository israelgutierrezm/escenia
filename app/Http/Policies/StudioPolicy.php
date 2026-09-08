<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Studio\Models\Studio;

/**
 * Tenant isolation of the $studio is guaranteed upstream by the tenant global
 * scope; these checks focus on the permission the user holds within the tenant.
 */
class StudioPolicy
{
    public function view(User $user, Studio $studio): bool
    {
        return $user->can(Permission::StudioView->value);
    }

    public function manage(User $user, Studio $studio): bool
    {
        return $user->can(Permission::StudioManage->value);
    }

    public function viewProduction(User $user, Studio $studio): bool
    {
        return $user->can(Permission::ProductionView->value);
    }

    public function produce(User $user, Studio $studio): bool
    {
        return $user->can(Permission::ProductionManage->value);
    }
}
