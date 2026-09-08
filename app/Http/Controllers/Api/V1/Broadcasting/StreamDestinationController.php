<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Broadcasting;

use App\Application\Broadcasting\Actions\CreateStreamDestinationAction;
use App\Application\Broadcasting\DTOs\CreateStreamDestinationData;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Broadcasting\Enums\DestinationProtocol;
use App\Domain\Broadcasting\Models\StreamDestination;
use App\Domain\Events\Models\Event;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\CreateStreamDestinationRequest;
use App\Http\Resources\StreamDestinationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Stream destinations are workspace-level and reusable. Tenant isolation comes
 * from tenant-scoped queries; authorization is a direct broadcast permission
 * check. Stream keys are encrypted at rest and never returned.
 */
class StreamDestinationController extends Controller
{
    public function index(Request $request, string $event): AnonymousResourceCollection
    {
        $eventModel = $this->resolveEvent($event);
        $this->requirePermission($request, Permission::BroadcastView);

        $destinations = StreamDestination::query()->where('workspace_id', $eventModel->workspace_id)->latest()->get();

        return StreamDestinationResource::collection($destinations);
    }

    public function store(CreateStreamDestinationRequest $request, CreateStreamDestinationAction $create, string $event): JsonResponse
    {
        $eventModel = $this->resolveEvent($event);
        $this->requirePermission($request, Permission::BroadcastManage);

        $workspace = Workspace::query()->whereKey($eventModel->workspace_id)->firstOrFail();

        $destination = $create->execute($workspace, $request->user(), new CreateStreamDestinationData(
            name: (string) $request->validated('name'),
            protocol: DestinationProtocol::from((string) $request->validated('protocol')),
            url: (string) $request->validated('url'),
            streamKey: (string) $request->validated('stream_key'),
        ));

        return StreamDestinationResource::make($destination)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function requirePermission(Request $request, Permission $permission): void
    {
        abort_unless($request->user()->can($permission->value), Response::HTTP_FORBIDDEN);
    }
}
