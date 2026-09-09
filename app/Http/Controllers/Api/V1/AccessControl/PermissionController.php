<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\AccessControl;

use App\Domain\AccessControl\Enums\Permission;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The permission catalog, grouped for building role/permission editors. The
 * catalog is the single source of truth for what a custom role or per-user
 * grant may contain.
 */
class PermissionController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        /** @var array<string, list<array{value: string, label: string}>> $grouped */
        $grouped = [];

        foreach (Permission::cases() as $permission) {
            $grouped[$permission->group()][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        $data = [];
        foreach ($grouped as $group => $permissions) {
            $data[] = ['group' => $group, 'permissions' => $permissions];
        }

        return response()->json(['data' => $data]);
    }
}
