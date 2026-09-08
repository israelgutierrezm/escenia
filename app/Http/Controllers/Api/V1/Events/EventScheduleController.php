<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreScheduleItemRequest;
use App\Http\Resources\EventScheduleItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventScheduleController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('view', $model);

        return EventScheduleItemResource::collection($model->scheduleItems()->orderBy('position')->get());
    }

    public function store(StoreScheduleItemRequest $request, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('update', $model);

        // Resolve the optional session by its public ULID, scoped to the event.
        $sessionId = null;
        $sessionUlid = $request->validated('event_session_id');
        if (is_string($sessionUlid) && $sessionUlid !== '') {
            $session = $model->sessions()->where('ulid', $sessionUlid)->first();
            abort_if($session === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unknown session for this event.');
            $sessionId = $session->getKey();
        }

        $item = $model->scheduleItems()->create([
            'tenant_id' => $model->tenant_id,
            'event_session_id' => $sessionId,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'position' => $request->validated('position') ?? 0,
        ]);

        return EventScheduleItemResource::make($item)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
