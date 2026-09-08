<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Application\Production\Actions\ReorderRunOfShowAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\RunOfShowItem;
use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\ReorderRunOfShowRequest;
use App\Http\Requests\Production\StoreRunOfShowItemRequest;
use App\Http\Resources\RunOfShowItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RunOfShowController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $studio = Studio::query()->where('event_id', $this->resolveEvent($event)->getKey())->first();

        $this->authorize('viewProduction', $studio ?? new Studio);

        $items = $studio !== null
            ? RunOfShowItem::query()->where('studio_id', $studio->getKey())->with('scene')->orderBy('position')->get()
            : collect();

        return RunOfShowItemResource::collection($items);
    }

    public function store(StoreRunOfShowItemRequest $request, EnsureStudioAction $ensure, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('produce', $studio);

        $sceneId = null;
        $sceneUlid = $request->validated('scene_id');
        if (is_string($sceneUlid) && $sceneUlid !== '') {
            $scene = Scene::query()->where('ulid', $sceneUlid)->where('studio_id', $studio->getKey())->first();
            abort_if($scene === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unknown scene for this studio.');
            $sceneId = $scene->getKey();
        }

        $position = (int) RunOfShowItem::query()->where('studio_id', $studio->getKey())->max('position') + 1;

        $item = RunOfShowItem::create([
            'tenant_id' => $studio->tenant_id,
            'studio_id' => $studio->getKey(),
            'scene_id' => $sceneId,
            'title' => (string) $request->validated('title'),
            'notes' => $request->validated('notes'),
            'duration_seconds' => $request->validated('duration_seconds') !== null ? (int) $request->validated('duration_seconds') : null,
            'position' => $position,
        ]);

        return RunOfShowItemResource::make($item->load('scene'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function reorder(ReorderRunOfShowRequest $request, EnsureStudioAction $ensure, ReorderRunOfShowAction $action, string $event): AnonymousResourceCollection
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('produce', $studio);

        /** @var list<string> $items */
        $items = $request->validated('items');
        $action->execute($studio, $request->user(), $items);

        $ordered = RunOfShowItem::query()->where('studio_id', $studio->getKey())->with('scene')->orderBy('position')->get();

        return RunOfShowItemResource::collection($ordered);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
