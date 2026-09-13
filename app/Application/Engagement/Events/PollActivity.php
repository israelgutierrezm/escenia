<?php

declare(strict_types=1);

namespace App\Application\Engagement\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a poll of an event changes (created / opened / closed / voted),
 * on the event's public channel. Carries no payload — clients refetch so live
 * result bars stay in sync.
 */
final class PollActivity implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly string $eventUlid) {}

    public function broadcastOn(): Channel
    {
        return new Channel("event.{$this->eventUlid}");
    }

    public function broadcastAs(): string
    {
        return 'polls.changed';
    }
}
