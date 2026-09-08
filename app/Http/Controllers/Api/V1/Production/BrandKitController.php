<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Application\Production\Actions\CreateBrandKitAction;
use App\Application\Production\Actions\SetDefaultBrandKitAction;
use App\Application\Production\DTOs\CreateBrandKitData;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\BrandKit;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\CreateBrandKitRequest;
use App\Http\Resources\BrandKitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Brand kits are workspace-level (reusable), not studio-specific. Tenant
 * isolation comes from the tenant-scoped queries; authorization is a direct
 * production permission check (scoped to the current tenant team).
 */
class BrandKitController extends Controller
{
    public function index(Request $request, string $event): AnonymousResourceCollection
    {
        $eventModel = $this->resolveEvent($event);
        $this->requirePermission($request, Permission::ProductionView);

        $kits = BrandKit::query()->where('workspace_id', $eventModel->workspace_id)->latest()->get();

        return BrandKitResource::collection($kits);
    }

    public function store(CreateBrandKitRequest $request, CreateBrandKitAction $create, string $event): JsonResponse
    {
        $eventModel = $this->resolveEvent($event);
        $this->requirePermission($request, Permission::ProductionManage);

        $workspace = Workspace::query()->whereKey($eventModel->workspace_id)->firstOrFail();

        /** @var array<string, mixed>|null $tokens */
        $tokens = $request->validated('tokens');

        $kit = $create->execute($workspace, $request->user(), new CreateBrandKitData(
            name: (string) $request->validated('name'),
            tokens: $tokens,
            isDefault: (bool) $request->validated('is_default'),
        ));

        return BrandKitResource::make($kit)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function setDefault(Request $request, SetDefaultBrandKitAction $action, string $kit): BrandKitResource
    {
        $model = BrandKit::query()->where('ulid', $kit)->firstOrFail();
        $this->requirePermission($request, Permission::ProductionManage);

        return BrandKitResource::make($action->execute($model, $request->user()));
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
