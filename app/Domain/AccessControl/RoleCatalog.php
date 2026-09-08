<?php

declare(strict_types=1);

namespace App\Domain\AccessControl;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Tenancy\Enums\TenantRole;

/**
 * Single source of truth for the tenant-scoped role -> permission mapping.
 * Used both by the seeder (to create the Spatie roles/permissions as data) and
 * by the registration flow (to assign the owner role). Keeping it here prevents
 * hardcoded role logic from spreading through the codebase.
 */
final class RoleCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return [
            TenantRole::Owner->value => Permission::values(),
            TenantRole::Admin->value => [
                Permission::MembersManage->value,
                Permission::WorkspacesView->value,
                Permission::WorkspacesCreate->value,
                Permission::WorkspacesUpdate->value,
                Permission::WorkspacesDelete->value,
                Permission::EventsView->value,
                Permission::EventsCreate->value,
                Permission::EventsUpdate->value,
                Permission::EventsDelete->value,
                Permission::EventsTransition->value,
                Permission::EventsManageCapabilities->value,
                Permission::StudioView->value,
                Permission::StudioManage->value,
                Permission::ProductionView->value,
                Permission::ProductionManage->value,
                Permission::BroadcastView->value,
                Permission::BroadcastManage->value,
                Permission::EngagementView->value,
                Permission::EngagementManage->value,
                Permission::AnalyticsView->value,
                Permission::FeatureFlagsManage->value,
                Permission::AuditView->value,
            ],
            TenantRole::Member->value => [
                Permission::WorkspacesView->value,
                Permission::EventsView->value,
                Permission::StudioView->value,
                Permission::ProductionView->value,
                Permission::BroadcastView->value,
                Permission::EngagementView->value,
                Permission::AnalyticsView->value,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_keys(self::map());
    }

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return Permission::values();
    }
}
