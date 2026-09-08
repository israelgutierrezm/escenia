<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Application\Production\Actions\CreateSceneAction;
use App\Application\Production\Actions\UpdateSceneDefinitionAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreSceneRequest;
use App\Http\Requests\Production\UpdateSceneRequest;
use App\Http\Resources\SceneResource;
use App\Http\Resources\SceneVersionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SceneController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $studio = Studio::query()->where('event_id', $this->resolveEvent($event)->getKey())->first();

        $this->authorize('viewProduction', $studio ?? new Studio);

        $scenes = $studio !== null
            ? Scene::query()->where('studio_id', $studio->getKey())->with('currentVersion')->orderBy('position')->get()
            : collect();

        return SceneResource::collection($scenes);
    }

    public function store(StoreSceneRequest $request, EnsureStudioAction $ensure, CreateSceneAction $create, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('produce', $studio);

        $scene = $create->execute($studio, $request->user(), (string) $request->validated('name'));

        return SceneResource::make($scene->load('currentVersion'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(string $scene): SceneResource
    {
        $model = $this->resolveScene($scene);

        $this->authorize('viewProduction', $this->studioFor($model));

        return SceneResource::make($model->load('currentVersion'));
    }

    public function update(UpdateSceneRequest $request, UpdateSceneDefinitionAction $update, string $scene): SceneResource
    {
        $model = $this->resolveScene($scene);

        $this->authorize('produce', $this->studioFor($model));

        /** @var array<string, mixed> $definition */
        $definition = $request->validated('definition');

        $update->execute($model, $request->user(), $definition);

        return SceneResource::make($model->load('currentVersion'));
    }

    public function versions(string $scene): AnonymousResourceCollection
    {
        $model = $this->resolveScene($scene);

        $this->authorize('viewProduction', $this->studioFor($model));

        return SceneVersionResource::collection($model->versions()->orderByDesc('version')->get());
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function resolveScene(string $ulid): Scene
    {
        return Scene::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function studioFor(Scene $scene): Studio
    {
        return Studio::query()->whereKey($scene->studio_id)->firstOrFail();
    }
}
