<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\AccessControl\RoleCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalog and the global role templates as DATA. Roles are
 * created without a team id (tenant_id null) so they act as global templates,
 * while role assignments are scoped per tenant at runtime (ADR-011).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(null);

        foreach (RoleCatalog::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleCatalog::map() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }
}
