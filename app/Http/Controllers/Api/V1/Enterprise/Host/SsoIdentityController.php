<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Enterprise\Actions\UnlinkSsoIdentityAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Enterprise\Models\SsoIdentity;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\SsoIdentityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The federated identities of an SSO connection (ADR-035): which accounts its
 * IdP subjects log into, and unlinking one (owner-level, `tenant.manage`).
 * Both are resolved within the current tenant.
 */
class SsoIdentityController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request, string $connection): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = SsoConnection::query()->where('ulid', $connection)->firstOrFail();

        return SsoIdentityResource::collection(
            SsoIdentity::query()
                ->where('sso_connection_id', $model->id)
                ->with('user')
                ->latest('last_login_at')
                ->paginate(50)
        );
    }

    public function destroy(Request $request, UnlinkSsoIdentityAction $action, string $connection, string $identity): Response
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = SsoConnection::query()->where('ulid', $connection)->firstOrFail();
        $link = SsoIdentity::query()
            ->where('sso_connection_id', $model->id)
            ->where('ulid', $identity)
            ->firstOrFail();

        $action->execute($request->user(), $link);

        return response()->noContent();
    }
}
