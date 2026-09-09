<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\AccessControl\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authorizes a tenant-level permission for the current user. Used by
 * tenant-scoped admin controllers whose resources are not event-bound (so there
 * is no model policy to hang the check on). The Spatie permission team is
 * already bound to the active tenant by the ResolveTenant middleware.
 */
trait AuthorizesTenantPermission
{
    protected function authorizePermission(Request $request, Permission $permission): void
    {
        abort_unless(
            $request->user()?->can($permission->value) === true,
            JsonResponse::HTTP_FORBIDDEN,
        );
    }
}
