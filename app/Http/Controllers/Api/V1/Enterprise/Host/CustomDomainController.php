<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Enterprise\Actions\CreateCustomDomainAction;
use App\Application\Enterprise\Actions\DeleteCustomDomainAction;
use App\Application\Enterprise\Actions\VerifyCustomDomainAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Enterprise\Models\CustomDomain;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\CreateCustomDomainRequest;
use App\Http\Resources\CustomDomainResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Tenant-level management of custom (white-label) domains. Managing enterprise
 * routing is owner-level administration (`tenant.manage`).
 */
class CustomDomainController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return CustomDomainResource::collection(
            CustomDomain::query()->with('workspace')->latest('id')->get()
        );
    }

    public function store(CreateCustomDomainRequest $request, CreateCustomDomainAction $action): JsonResponse
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $workspace = null;
        $workspaceId = $request->validated('workspace_id');

        if (is_string($workspaceId) && $workspaceId !== '') {
            $workspace = Workspace::query()->where('ulid', $workspaceId)->firstOrFail();
        }

        $domain = $action->execute($request->user(), (string) $request->validated('hostname'), $workspace);

        return CustomDomainResource::make($domain->load('workspace'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function verify(Request $request, VerifyCustomDomainAction $action, string $domain): CustomDomainResource
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = CustomDomain::query()->where('ulid', $domain)->firstOrFail();

        return CustomDomainResource::make($action->execute($request->user(), $model)->load('workspace'));
    }

    public function destroy(Request $request, DeleteCustomDomainAction $action, string $domain): Response
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = CustomDomain::query()->where('ulid', $domain)->firstOrFail();
        $action->execute($request->user(), $model);

        return response()->noContent();
    }
}
