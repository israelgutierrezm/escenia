<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Application\Workspaces\Actions\CreateWorkspaceAction;
use App\Application\Workspaces\DTOs\CreateWorkspaceData;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * All queries run under the active tenant's global scope, so a workspace from
 * another tenant is never resolved (isolation) and permission checks resolve
 * within the tenant team (authorization).
 */
class WorkspaceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Workspace::class);

        $workspaces = Workspace::query()
            ->orderBy('name')
            ->paginate(20);

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspaceAction $action, TenantContext $context): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        /** @var array{name: string, slug?: string|null} $data */
        $data = $request->validated();

        $workspace = $action->execute(
            $context->tenant(),
            $request->user(),
            CreateWorkspaceData::fromArray($data),
        );

        return WorkspaceResource::make($workspace)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(string $workspace): WorkspaceResource
    {
        $model = $this->resolve($workspace);

        $this->authorize('view', $model);

        return WorkspaceResource::make($model);
    }

    public function update(UpdateWorkspaceRequest $request, string $workspace): WorkspaceResource
    {
        $model = $this->resolve($workspace);

        $this->authorize('update', $model);

        $model->update($request->validated());

        return WorkspaceResource::make($model);
    }

    public function destroy(string $workspace): Response
    {
        $model = $this->resolve($workspace);

        $this->authorize('delete', $model);

        $model->delete();

        return response()->noContent();
    }

    /**
     * Explicit tenant-scoped lookup by public ULID. Resolving here (rather than
     * via implicit route-model binding) guarantees the tenant context is already
     * established, so a cross-tenant id yields 404 instead of leaking.
     */
    private function resolve(string $ulid): Workspace
    {
        return Workspace::query()->where('ulid', $ulid)->firstOrFail();
    }
}
