<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise;

use App\Domain\Enterprise\Enums\DomainStatus;
use App\Domain\Enterprise\Models\CustomDomain;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public routing resolution: given a hostname, return which tenant (and
 * optionally workspace) an ACTIVE custom domain maps to. Used by the edge/
 * ingress to route white-label traffic. The domain is resolved unscoped (there
 * is no tenant context yet) and only active domains are disclosed — minimal,
 * already-public routing metadata, never secrets.
 */
class DomainResolveController extends Controller
{
    public function resolve(Request $request): JsonResponse
    {
        $hostname = $request->query('hostname');

        if (! is_string($hostname) || $hostname === '') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'A hostname query parameter is required.');
        }

        $domain = CustomDomain::query()
            ->withoutGlobalScopes()
            ->with(['tenant', 'workspace'])
            ->where('hostname', Str::lower($hostname))
            ->where('status', DomainStatus::Active)
            ->first();

        if ($domain === null) {
            abort(Response::HTTP_NOT_FOUND, 'No active domain for this hostname.');
        }

        return response()->json([
            'data' => [
                'hostname' => $domain->hostname,
                'tenant_id' => $domain->tenant->ulid,
                'tenant_name' => $domain->tenant->name,
                'workspace_id' => $domain->workspace?->ulid,
            ],
        ]);
    }
}
