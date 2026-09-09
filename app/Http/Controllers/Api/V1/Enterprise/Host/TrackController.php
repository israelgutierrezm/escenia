<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Agenda\Actions\CreateTrackAction;
use App\Domain\Agenda\Models\Track;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\CreateTrackRequest;
use App\Http\Resources\TrackResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrackController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $model);

        return TrackResource::collection(
            Track::query()->where('event_id', $model->getKey())->orderBy('position')->get()
        );
    }

    public function store(CreateTrackRequest $request, CreateTrackAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEnterprise', $model);

        $color = $request->validated('color');
        $track = $action->execute($model, $request->user(), (string) $request->validated('name'), $color !== null ? (string) $color : null);

        return TrackResource::make($track)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
