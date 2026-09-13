<?php

declare(strict_types=1);

namespace App\Application\Engagement\Events;

use App\Domain\Engagement\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a chat message is posted, on the event's public channel
 * (`event.{ulid}`). The payload mirrors ChatMessageResource so the client can
 * append it directly. Only scalars are carried so the queued broadcast never
 * has to re-fetch a model.
 */
final class ChatMessagePosted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $eventUlid,
        public readonly string $id,
        public readonly string $authorName,
        public readonly bool $isHost,
        public readonly string $body,
        public readonly ?string $createdAt,
    ) {}

    public static function fromMessage(ChatMessage $message, string $eventUlid): self
    {
        return new self(
            eventUlid: $eventUlid,
            id: $message->ulid,
            authorName: $message->author_name,
            isHost: $message->user_id !== null,
            body: $message->body,
            createdAt: $message->created_at?->toIso8601String(),
        );
    }

    public function broadcastOn(): Channel
    {
        return new Channel("event.{$this->eventUlid}");
    }

    public function broadcastAs(): string
    {
        return 'chat.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->authorName,
            'is_host' => $this->isHost,
            'body' => $this->body,
            'created_at' => $this->createdAt,
        ];
    }
}
