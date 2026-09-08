<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Host;

use App\Application\Engagement\Actions\PostChatMessageAction;
use App\Domain\Engagement\Models\ChatMessage;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\PostChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host side of the event chat: read the feed and post as the host.
 */
class ChatController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $messages = ChatMessage::query()
            ->where('event_id', $model->getKey())
            ->latest('id')
            ->paginate(50);

        return ChatMessageResource::collection($messages);
    }

    public function store(PostChatMessageRequest $request, PostChatMessageAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEngagement', $model);

        $message = $action->forHost($model, $request->user(), (string) $request->validated('body'));

        return ChatMessageResource::make($message)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
