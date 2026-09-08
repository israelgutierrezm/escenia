<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Application\Engagement\Actions\PostChatMessageAction;
use App\Domain\Engagement\Models\ChatMessage;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\PostChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of the event chat: read the feed for their event and post.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $messages = ChatMessage::query()
            ->where('event_id', $attendee->event_id)
            ->latest('id')
            ->paginate(50);

        return ChatMessageResource::collection($messages);
    }

    public function store(PostChatMessageRequest $request, PostChatMessageAction $action): JsonResponse
    {
        $message = $action->forAttendee($this->context->attendeeOrFail(), (string) $request->validated('body'));

        return ChatMessageResource::make($message)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
