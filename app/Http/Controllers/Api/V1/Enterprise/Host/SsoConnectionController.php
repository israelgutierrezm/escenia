<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Enterprise\Actions\CreateSsoConnectionAction;
use App\Application\Enterprise\Actions\DeleteSsoConnectionAction;
use App\Application\Enterprise\Actions\UpdateSsoConnectionAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\CreateSsoConnectionRequest;
use App\Http\Requests\Enterprise\UpdateSsoConnectionRequest;
use App\Http\Resources\SsoConnectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Tenant-level management of SSO connections. Configuring an external login path
 * is owner-level administration (`tenant.manage`). The encrypted provider
 * config is never returned.
 */
class SsoConnectionController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return SsoConnectionResource::collection(
            SsoConnection::query()->latest('id')->get()
        );
    }

    public function store(CreateSsoConnectionRequest $request, CreateSsoConnectionAction $action): JsonResponse
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $validated = $request->validated();

        $connection = $action->execute(
            $request->user(),
            SsoProvider::from((string) $validated['provider']),
            (string) $validated['display_name'],
            isset($validated['domain']) ? (string) $validated['domain'] : null,
            isset($validated['config']) && is_array($validated['config']) ? $validated['config'] : [],
            isset($validated['default_role']) ? TenantRole::from((string) $validated['default_role']) : TenantRole::Member,
        );

        return SsoConnectionResource::make($connection)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateSsoConnectionRequest $request, UpdateSsoConnectionAction $action, string $connection): SsoConnectionResource
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = SsoConnection::query()->where('ulid', $connection)->firstOrFail();

        $validated = $request->validated();
        $attributes = array_intersect_key($validated, array_flip(['display_name', 'domain', 'config', 'is_active']));

        if (array_key_exists('default_role', $validated)) {
            $attributes['default_role'] = TenantRole::from((string) $validated['default_role']);
        }

        return SsoConnectionResource::make($action->execute($request->user(), $model, $attributes));
    }

    public function destroy(Request $request, DeleteSsoConnectionAction $action, string $connection): Response
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = SsoConnection::query()->where('ulid', $connection)->firstOrFail();
        $action->execute($request->user(), $model);

        return response()->noContent();
    }
}
