<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\AccessControl;

use App\Application\AccessControl\Actions\CreateRoleAction;
use App\Application\AccessControl\Actions\DeleteRoleAction;
use App\Application\AccessControl\Actions\UpdateRoleAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\CreateRoleRequest;
use App\Http\Requests\AccessControl\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

/**
 * Manages the tenant's roles: the read-only system roles (owner/admin/member)
 * plus the tenant's own custom roles. Gated by `members.manage`; the
 * anti-escalation rules live in the actions/AccessGuard.
 */
class RoleController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request, TenantContext $tenantContext): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();

        $roles = Role::query()
            ->with('permissions')
            ->where(function (Builder $query) use ($tenant): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->getKey());
            })
            ->orderBy('tenant_id')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(CreateRoleRequest $request, CreateRoleAction $action, TenantContext $tenantContext): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $role = $action->execute($request->user(), $tenant, (string) $request->validated('name'), $this->permissions($request));

        return RoleResource::make($role->load('permissions'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateRoleRequest $request, UpdateRoleAction $action, TenantContext $tenantContext, string $role): RoleResource
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $model = $this->resolveCustomRole($tenant, $role);
        $updated = $action->execute($request->user(), $tenant, $model, $this->permissions($request));

        return RoleResource::make($updated->load('permissions'));
    }

    public function destroy(Request $request, DeleteRoleAction $action, TenantContext $tenantContext, string $role): Response
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $model = $this->resolveCustomRole($tenant, $role);
        $action->execute($request->user(), $tenant, $model);

        return response()->noContent();
    }

    /**
     * @return list<string>
     */
    private function permissions(FormRequest $request): array
    {
        /** @var array<int, mixed> $raw */
        $raw = (array) $request->validated('permissions');

        return array_map(strval(...), array_values($raw));
    }

    private function resolveCustomRole(Tenant $tenant, string $name): Role
    {
        return Role::query()
            ->where('name', $name)
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
    }
}
