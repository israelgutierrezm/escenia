<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Domain\Events\Enums\SessionStatus;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreSessionRequest;
use App\Http\Resources\EventSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventSessionController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('view', $model);

        return EventSessionResource::collection($model->sessions()->orderBy('position')->get());
    }

    public function store(StoreSessionRequest $request, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('update', $model);

        $session = $model->sessions()->create([
            'tenant_id' => $model->tenant_id,
            'title' => $request->validated('title'),
            'status' => $request->validated('status') ?? SessionStatus::Scheduled->value,
            'scheduled_start_at' => $request->validated('scheduled_start_at'),
            'scheduled_end_at' => $request->validated('scheduled_end_at'),
            'position' => $request->validated('position') ?? 0,
        ]);

        return EventSessionResource::make($session)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
