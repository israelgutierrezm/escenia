<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Host;

use App\Application\Engagement\Actions\AddResourceAction;
use App\Domain\Engagement\Models\Resource;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\AddResourceRequest;
use App\Http\Resources\ResourceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host side of event resources (handouts): list and add.
 */
class ResourceController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $resources = Resource::query()
            ->where('event_id', $model->getKey())
            ->orderBy('position')
            ->get();

        return ResourceResource::collection($resources);
    }

    public function store(AddResourceRequest $request, AddResourceAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEngagement', $model);

        $resource = $action->execute(
            $model,
            $request->user(),
            (string) $request->validated('title'),
            (string) $request->validated('url'),
        );

        return ResourceResource::make($resource)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
