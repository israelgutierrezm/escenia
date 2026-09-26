<?php

declare(strict_types=1);

namespace App\Application\Networking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A networking notification delivered to one attendee's PRIVATE channel
 * (`attendee.{ulid}`): a connection request/response or a meeting
 * proposal/response/cancellation. Scalar payload so the queued broadcast never
 * re-fetches; the app shows a toast and refreshes its lists.
 */
final class NetworkingNotification implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientUlid,
        public readonly string $kind,
        public readonly string $message,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("attendee.{$this->recipientUlid}");
    }

    public function broadcastAs(): string
    {
        return 'networking.notification';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'message' => $this->message];
    }
}
