<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Application\Events\Actions\CreateEventAction;
use App\Application\Events\DTOs\CreateEventData;
use App\Domain\Events\Models\Event;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $query = Event::query()->latest();

        $workspaceUlid = $request->query('workspace');
        if (is_string($workspaceUlid) && $workspaceUlid !== '') {
            $workspace = Workspace::query()->where('ulid', $workspaceUlid)->first();
            $query->where('workspace_id', $workspace?->getKey() ?? 0);
        }

        return EventResource::collection($query->paginate(20));
    }

    public function store(StoreEventRequest $request, CreateEventAction $action, TenantContext $context): JsonResponse
    {
        $this->authorize('create', Event::class);

        $workspace = Workspace::query()
            ->where('ulid', $request->validated('workspace_id'))
            ->firstOrFail();

        $event = $action->execute(
            $context->tenantOrFail(),
            $workspace,
            $request->user(),
            CreateEventData::fromArray($request->validated()),
        );

        return EventResource::make($event)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(string $event): EventResource
    {
        $model = $this->resolve($event);

        $this->authorize('view', $model);

        $model->load(['workspace', 'capabilities', 'sessions', 'speakers', 'scheduleItems']);

        return EventResource::make($model);
    }

    public function update(UpdateEventRequest $request, string $event): EventResource
    {
        $model = $this->resolve($event);

        $this->authorize('update', $model);

        $model->update($request->validated());

        return EventResource::make($model);
    }

    public function destroy(string $event): Response
    {
        $model = $this->resolve($event);

        $this->authorize('delete', $model);

        $model->delete();

        return response()->noContent();
    }

    private function resolve(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
