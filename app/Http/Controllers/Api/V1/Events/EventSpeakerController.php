<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Domain\Events\Enums\SpeakerRole;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreSpeakerRequest;
use App\Http\Resources\EventSpeakerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventSpeakerController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('view', $model);

        return EventSpeakerResource::collection($model->speakers()->orderBy('position')->get());
    }

    public function store(StoreSpeakerRequest $request, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('update', $model);

        $speaker = $model->speakers()->create([
            'tenant_id' => $model->tenant_id,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'headline' => $request->validated('headline'),
            'bio' => $request->validated('bio'),
            'avatar_url' => $request->validated('avatar_url'),
            'role' => $request->validated('role') ?? SpeakerRole::Speaker->value,
            'position' => $request->validated('position') ?? 0,
        ]);

        return EventSpeakerResource::make($speaker)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
