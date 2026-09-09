<?php

declare(strict_types=1);

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\Models\Track;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventSession;

/**
 * Sets a session's agenda placement: its track, room and capacity.
 */
final class SetSessionAgendaAction
{
    public function execute(Event $event, EventSession $session, ?string $trackUlid, ?string $room, ?int $capacity): EventSession
    {
        $trackId = null;

        if ($trackUlid !== null) {
            $trackId = Track::query()->where('ulid', $trackUlid)->where('event_id', $event->getKey())->value('id');
        }

        $session->forceFill([
            'track_id' => $trackId,
            'room' => $room,
            'capacity' => $capacity,
        ])->save();

        return $session;
    }
}
