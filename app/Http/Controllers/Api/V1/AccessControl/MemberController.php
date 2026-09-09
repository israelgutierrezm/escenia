<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\AccessControl;

use App\Application\AccessControl\Actions\ChangeMembershipRoleAction;
use App\Application\AccessControl\Actions\SyncMemberPermissionsAction;
use App\Application\AccessControl\Actions\SyncMemberRolesAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\AccessControl\Services\AccessGuard;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessControl\ChangeMembershipRoleRequest;
use App\Http\Requests\AccessControl\SyncMemberPermissionsRequest;
use App\Http\Requests\AccessControl\SyncMemberRolesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-user access management: list members, inspect a member's effective access,
 * change their base tier, and sync their custom roles / direct permissions.
 * Gated by `members.manage`; anti-escalation rules live in the actions.
 */
class MemberController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();

        $data = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('user')
            ->get()
            ->map(fn (TenantMembership $membership): array => [
                'id' => $membership->user?->ulid,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'membership_role' => $membership->role->value,
                'status' => $membership->status->value,
            ])
            ->all();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, TenantContext $tenantContext, string $member): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $user = $this->resolveMember($tenant, $member);

        return response()->json(['data' => $this->accessPayload($tenant, $user)]);
    }

    public function membershipRole(ChangeMembershipRoleRequest $request, ChangeMembershipRoleAction $action, TenantContext $tenantContext, string $member): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $user = $this->resolveMember($tenant, $member);

        $action->execute($request->user(), $tenant, $user, TenantRole::from((string) $request->validated('role')));

        return response()->json(['data' => $this->accessPayload($tenant, $user->refresh())]);
    }

    public function syncRoles(SyncMemberRolesRequest $request, SyncMemberRolesAction $action, TenantContext $tenantContext, string $member): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $user = $this->resolveMember($tenant, $member);

        /** @var array<int, mixed> $rawRoles */
        $rawRoles = (array) $request->validated('roles');
        $action->execute($request->user(), $tenant, $user, array_map(strval(...), array_values($rawRoles)));

        return response()->json(['data' => $this->accessPayload($tenant, $user)]);
    }

    public function syncPermissions(SyncMemberPermissionsRequest $request, SyncMemberPermissionsAction $action, TenantContext $tenantContext, string $member): JsonResponse
    {
        $this->authorizePermission($request, Permission::MembersManage);

        $tenant = $tenantContext->tenantOrFail();
        $user = $this->resolveMember($tenant, $member);

        /** @var array<int, mixed> $rawPermissions */
        $rawPermissions = (array) $request->validated('permissions');
        $action->execute($request->user(), $tenant, $user, array_map(strval(...), array_values($rawPermissions)));

        return response()->json(['data' => $this->accessPayload($tenant, $user)]);
    }

    private function resolveMember(Tenant $tenant, string $ulid): User
    {
        $user = User::query()->where('ulid', $ulid)->firstOrFail();

        abort_unless($user->belongsToTenant($tenant), Response::HTTP_NOT_FOUND);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function accessPayload(Tenant $tenant, User $user): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $membership = $user->tenantMembershipFor($tenant);

        return [
            'id' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'membership_role' => $membership?->role->value,
            'custom_roles' => $user->getRoleNames()
                ->reject(fn (string $name): bool => AccessGuard::isSystemRole($name))
                ->values()
                ->all(),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values()->all(),
            'effective_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}
