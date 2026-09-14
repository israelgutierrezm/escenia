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
