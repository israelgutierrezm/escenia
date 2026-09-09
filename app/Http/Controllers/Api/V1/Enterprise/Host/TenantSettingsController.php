<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Enterprise\Actions\UpdateTenantSettingsAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Tenancy\Context\TenantContext;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettingsResource;
use Illuminate\Http\Request;

/**
 * The tenant's enterprise settings (data residency + dedicated flag). Read and
 * managed by the owner (`tenant.manage`).
 */
class TenantSettingsController extends Controller
{
    use AuthorizesTenantPermission;

    public function show(Request $request, TenantContext $tenantContext): TenantSettingsResource
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return TenantSettingsResource::make($tenantContext->tenantOrFail());
    }

    public function update(UpdateTenantSettingsRequest $request, UpdateTenantSettingsAction $action, TenantContext $tenantContext): TenantSettingsResource
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return TenantSettingsResource::make(
            $action->execute($request->user(), $tenantContext->tenantOrFail(), $request->validated())
        );
    }
}
