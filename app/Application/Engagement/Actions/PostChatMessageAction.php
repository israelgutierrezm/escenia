<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Models\ChatMessage;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Registration\Models\Attendee;

/**
 * Posts a chat message to an event, authored either by an attendee (audience) or
 * by a host (platform user). `author_name` is denormalized for display so the
 * feed never has to join back to the author. Attendee messages feed the
 * analytics plane as audience engagement; host messages do not.
 */
final class PostChatMessageAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function forAttendee(Attendee $attendee, string $body): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'event_id' => $attendee->event_id,
            'attendee_id' => $attendee->getKey(),
            'author_name' => $attendee->name,
            'body' => $body,
        ]);

        $this->analytics->record(AnalyticsEventName::EngagementChat, $attendee->event()->firstOrFail(), $attendee);

        return $message;
    }

    public function forHost(Event $event, User $host, string $body): ChatMessage
    {
        return ChatMessage::query()->create([
            'event_id' => $event->getKey(),
            'user_id' => $host->getKey(),
            'author_name' => $host->name,
            'body' => $body,
        ]);
    }
}
