<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenancy\Context\TenantContext;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\AuditLogQueryRequest;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Advanced, filterable query over the immutable audit trail, restricted to the
 * current tenant's records. Gated by `audit.view` (owner/admin). The audit
 * table is not globally tenant-scoped, so the tenant filter is applied here.
 */
class AuditLogController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(AuditLogQueryRequest $request, TenantContext $tenantContext): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::AuditView);

        $filters = $request->validated();

        $query = AuditLog::query()
            ->where('tenant_id', $tenantContext->tenantOrFail()->getKey())
            ->latest('id');

        if (isset($filters['action']) && $filters['action'] !== '') {
            $query->where('action', (string) $filters['action']);
        }

        if (isset($filters['actor']) && $filters['actor'] !== '') {
            $query->where('actor_label', 'like', '%'.$filters['actor'].'%');
        }

        if (isset($filters['auditable_type']) && $filters['auditable_type'] !== '') {
            $query->where('auditable_type', 'like', '%'.$filters['auditable_type'].'%');
        }

        if (isset($filters['from']) && $filters['from'] !== '') {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['from']));
        }

        if (isset($filters['to']) && $filters['to'] !== '') {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['to']));
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 25;

        return AuditLogResource::collection($query->paginate($perPage));
    }
}
