<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Enums;

/**
 * The permission catalog. Permissions are stable strings (no magic strings
 * scattered in code) resolved through Spatie, scoped by tenant team
 * (see ADR-011). Only the permissions needed by Foundation exist today.
 */
enum Permission: string
{
    case TenantManage = 'tenant.manage';
    case MembersManage = 'members.manage';
    case WorkspacesView = 'workspaces.view';
    case WorkspacesCreate = 'workspaces.create';
    case WorkspacesUpdate = 'workspaces.update';
    case WorkspacesDelete = 'workspaces.delete';
    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsTransition = 'events.transition';
    case EventsManageCapabilities = 'events.capabilities.manage';
    case StudioView = 'studio.view';
    case StudioManage = 'studio.manage';
    case ProductionView = 'production.view';
    case ProductionManage = 'production.manage';
    case BroadcastView = 'broadcast.view';
    case BroadcastManage = 'broadcast.manage';
    case EngagementView = 'engagement.view';
    case EngagementManage = 'engagement.manage';
    case AnalyticsView = 'analytics.view';
    case FeatureFlagsManage = 'feature_flags.manage';
    case AuditView = 'audit.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
