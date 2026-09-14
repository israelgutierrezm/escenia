<?php

declare(strict_types=1);

namespace App\Application\Registration\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a NEW attendee registers, on the event's public channel.
 * Carries only the running total — never attendee PII — so organizers watch the
 * count climb live; the admin refetches the (authenticated) registrant list on
 * receipt, keeping names/emails off the wire.
 */
final class AttendeeRegistered implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $eventUlid,
        public readonly int $total,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("event.{$this->eventUlid}");
    }

    public function broadcastAs(): string
    {
        return 'registration.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['total' => $this->total];
    }
}
