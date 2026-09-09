<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Actions\CreateClipAction;
use App\Domain\Content\Models\Clip;
use App\Domain\Content\Models\Recording;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateClipRequest;
use App\Http\Resources\ClipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClipController extends Controller
{
    public function index(string $recording): AnonymousResourceCollection
    {
        $model = Recording::query()->where('ulid', $recording)->firstOrFail();

        $this->authorize('viewContent', $model->event()->firstOrFail());

        $clips = Clip::query()->where('recording_id', $model->getKey())->latest('id')->get();

        return ClipResource::collection($clips);
    }

    public function store(CreateClipRequest $request, CreateClipAction $action, string $recording): JsonResponse
    {
        $model = Recording::query()->where('ulid', $recording)->firstOrFail();

        $this->authorize('manageContent', $model->event()->firstOrFail());

        $clip = $action->execute(
            $model,
            $request->user(),
            (string) $request->validated('title'),
            (int) $request->validated('start_ms'),
            (int) $request->validated('end_ms'),
        );

        return ClipResource::make($clip)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
