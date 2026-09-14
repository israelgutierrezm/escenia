<?php

declare(strict_types=1);

namespace App\Application\Studio\Events;

use App\Domain\Studio\Models\StudioParticipant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a studio participant joins, moves between stages, or leaves,
 * on the PRIVATE producer channel `studio.{ulid}` — backstage state is
 * production information, not public like the attendee chat. Only scalars are
 * carried so the queued broadcast never re-fetches a model; the console
 * refetches its participant list on receipt (robust, no client reconciliation).
 */
final class StudioParticipantActivity implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $eventUlid,
        public readonly string $id,
        public readonly string $name,
        public readonly string $stage,
        public readonly string $action,
    ) {}

    public static function fromParticipant(StudioParticipant $participant, string $eventUlid, string $action): self
    {
        return new self(
            eventUlid: $eventUlid,
            id: $participant->ulid,
            name: $participant->name,
            stage: $participant->stage->value,
            action: $action,
        );
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("studio.{$this->eventUlid}");
    }

    public function broadcastAs(): string
    {
        return 'participant.activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stage' => $this->stage,
            'action' => $this->action,
        ];
    }
}
