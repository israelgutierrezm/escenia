<?php

declare(strict_types=1);

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\PermissionRegistrar;

/**
 * Private studio channel: only a user who can manage the studio of THIS event
 * may subscribe (backstage state is production information, not public). The
 * tenant is taken from the event — never client-supplied — and we set the
 * Spatie team context to it before checking the permission, exactly as
 * ResolveTenant does for HTTP requests.
 */
Broadcast::channel('studio.{eventUlid}', function (User $user, string $eventUlid): bool {
    $event = Event::query()->withoutGlobalScopes()->where('ulid', $eventUlid)->first();

    if ($event === null) {
        return false;
    }

    app(PermissionRegistrar::class)->setPermissionsTeamId($event->tenant_id);

    return $user->can(Permission::StudioManage->value);
});

/**
 * Presence channel for the live viewer count (`presence-viewers.{ulid}`).
 * Attendees join through their own token-authenticated endpoint (they are the
 * viewers, role `attendee`); a producer joins here via the Sanctum session to
 * watch the count and is tagged role `host` so it never inflates it. Returning
 * an array (not a bool) makes this a presence membership.
 *
 * @return array{id: string, name: string, role: string}|null
 */
Broadcast::channel('viewers.{eventUlid}', function (User $user, string $eventUlid): ?array {
    $event = Event::query()->withoutGlobalScopes()->where('ulid', $eventUlid)->first();

    if ($event === null) {
        return null;
    }

    app(PermissionRegistrar::class)->setPermissionsTeamId($event->tenant_id);

    if (! $user->can(Permission::StudioManage->value)) {
        return null;
    }

    return ['id' => 'host-'.$user->getKey(), 'name' => $user->name, 'role' => 'host'];
});
