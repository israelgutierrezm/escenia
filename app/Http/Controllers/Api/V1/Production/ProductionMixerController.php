<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Application\Production\Actions\SetPreviewSceneAction;
use App\Application\Production\Actions\TakeSceneAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\PreviewSceneRequest;
use App\Http\Requests\Production\TakeSceneRequest;
use App\Http\Resources\StudioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductionMixerController extends Controller
{
    public function preview(PreviewSceneRequest $request, EnsureStudioAction $ensure, SetPreviewSceneAction $action, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('produce', $studio);

        $scene = $this->sceneInStudio((string) $request->validated('scene_id'), $studio);
        $action->execute($studio, $request->user(), $scene);

        return $this->mixerState($studio);
    }

    public function take(TakeSceneRequest $request, EnsureStudioAction $ensure, TakeSceneAction $action, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('produce', $studio);

        $sceneUlid = $request->validated('scene_id');
        $scene = is_string($sceneUlid) && $sceneUlid !== ''
            ? $this->sceneInStudio($sceneUlid, $studio)
            : $studio->previewScene()->first();

        abort_if($scene === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'No scene to take: provide scene_id or set a preview first.');

        $action->execute($studio, $request->user(), $scene);

        return $this->mixerState($studio);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function sceneInStudio(string $ulid, Studio $studio): Scene
    {
        return Scene::query()->where('ulid', $ulid)->where('studio_id', $studio->getKey())->firstOrFail();
    }

    private function mixerState(Studio $studio): JsonResponse
    {
        return StudioResource::make($studio->refresh()->load(['previewScene', 'programScene']))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
