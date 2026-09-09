<?php

declare(strict_types=1);

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\Models\Track;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Creates a track for an event. New tracks go to the end.
 */
final class CreateTrackAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, string $name, ?string $color): Track
    {
        $position = (int) Track::query()->where('event_id', $event->getKey())->max('position');

        $track = Track::query()->create([
            'event_id' => $event->getKey(),
            'name' => $name,
            'color' => $color,
            'position' => $position + 1,
        ]);

        $this->audit->log('agenda.track.created', actor: $actor, tenant: $event->tenant, auditable: $track);

        return $track;
    }
}
