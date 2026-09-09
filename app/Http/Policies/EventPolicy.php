<?php

declare(strict_types=1);

namespace App\Http\Policies;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Tenant isolation of the $event is guaranteed upstream by the tenant global
 * scope; these checks focus on the permission the user holds within the tenant.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EventsView->value);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EventsCreate->value);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsUpdate->value);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsDelete->value);
    }

    public function transition(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsTransition->value);
    }

    public function manageCapabilities(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsManageCapabilities->value);
    }

    public function viewEngagement(User $user, Event $event): bool
    {
        return $user->can(Permission::EngagementView->value);
    }

    public function manageEngagement(User $user, Event $event): bool
    {
        return $user->can(Permission::EngagementManage->value);
    }

    public function viewAnalytics(User $user, Event $event): bool
    {
        return $user->can(Permission::AnalyticsView->value);
    }

    public function viewCommerce(User $user, Event $event): bool
    {
        return $user->can(Permission::CommerceView->value);
    }

    public function manageCommerce(User $user, Event $event): bool
    {
        return $user->can(Permission::CommerceManage->value);
    }

    public function viewContent(User $user, Event $event): bool
    {
        return $user->can(Permission::ContentView->value);
    }

    public function manageContent(User $user, Event $event): bool
    {
        return $user->can(Permission::ContentManage->value);
    }
}
