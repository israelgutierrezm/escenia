<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Enums;

use Illuminate\Support\Str;

/**
 * The permission catalog. Permissions are stable strings (no magic strings
 * scattered in code) resolved through Spatie, scoped by tenant team
 * (see ADR-011). This enum is the single source of truth for what a custom role
 * or a per-user grant may contain.
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
    case CommerceView = 'commerce.view';
    case CommerceManage = 'commerce.manage';
    case AutomationsView = 'automations.view';
    case AutomationsManage = 'automations.manage';
    case ContentView = 'content.view';
    case ContentManage = 'content.manage';
    case EducationView = 'education.view';
    case EducationManage = 'education.manage';
    case EnterpriseView = 'enterprise.view';
    case EnterpriseManage = 'enterprise.manage';
    case FeatureFlagsManage = 'feature_flags.manage';
    case AuditView = 'audit.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

    /**
     * The group a permission belongs to (its prefix), for grouping in the UI —
     * e.g. `events.capabilities.manage` → `events`.
     */
    public function group(): string
    {
        return Str::before($this->value, '.');
    }

    /**
     * A human-readable label, e.g. `events.view` → `Events View`.
     */
    public function label(): string
    {
        return Str::of($this->value)->replace(['.', '_'], ' ')->title()->toString();
    }
}
