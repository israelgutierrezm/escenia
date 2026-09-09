<?php

declare(strict_types=1);

namespace App\Application\Content\Handlers;

use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Content\Enums\RecordingSource;
use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Content\Enums\TrackKind;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\Models\RecordingTrack;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioSession;

/**
 * Outbox consumer (ADR-026): when a recorded broadcast ends, registers a
 * Recording (processing) plus its composite track. The real egress asset is
 * delivered by the provider later (future webhook marks it ready). Idempotent
 * per broadcast session. Runs inside the event's tenant context.
 */
final class RegisterBroadcastRecordingHandler implements OutboxHandler
{
    public function handle(OutboxEvent $event): void
    {
        $payload = $event->payload ?? [];

        if (($payload['record'] ?? false) !== true) {
            return;
        }

        $broadcastSessionId = (int) ($payload['broadcast_session_id'] ?? 0);
        $eventId = $this->resolveEventId($broadcastSessionId);

        if ($broadcastSessionId === 0 || $eventId === null) {
            return;
        }

        $recording = Recording::query()->firstOrCreate(
            ['broadcast_session_id' => $broadcastSessionId],
            [
                'event_id' => $eventId,
                'source' => RecordingSource::Broadcast,
                'status' => RecordingStatus::Processing,
                'title' => 'Broadcast recording',
            ],
        );

        if ($recording->wasRecentlyCreated) {
            RecordingTrack::query()->create([
                'recording_id' => $recording->getKey(),
                'kind' => TrackKind::Composite,
                'label' => 'Composite',
                'status' => RecordingStatus::Processing,
            ]);
        }
    }

    private function resolveEventId(int $broadcastSessionId): ?int
    {
        $broadcast = BroadcastSession::query()->find($broadcastSessionId);

        if ($broadcast === null) {
            return null;
        }

        $session = StudioSession::query()->find($broadcast->studio_session_id);

        if ($session === null) {
            return null;
        }

        $eventId = Studio::query()->whereKey($session->studio_id)->value('event_id');

        return $eventId === null ? null : (int) $eventId;
    }
}
