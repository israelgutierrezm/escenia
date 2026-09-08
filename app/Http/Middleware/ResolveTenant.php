<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request (ADR-010).
 *
 * The client may indicate the desired tenant via the X-Tenant-Id header (its
 * public ULID) or a {tenant} route parameter, but that hint is NEVER trusted:
 * the tenant is only established once the authenticated user's membership is
 * verified server-side. Establishing context also binds the Spatie permission
 * team so authorization checks resolve within this tenant.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $identifier = $this->identifier($request);

        if ($user instanceof User && $identifier !== null) {
            $tenant = Tenant::query()->where('ulid', $identifier)->first();

            if ($tenant === null || ! $user->belongsToTenant($tenant)) {
                abort(Response::HTTP_FORBIDDEN, 'You do not have access to this tenant.');
            }

            $this->tenantContext->setTenant($tenant);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
            Context::add('tenant_id', $tenant->ulid);
        }

        return $next($request);
    }

    private function identifier(Request $request): ?string
    {
        $route = $request->route('tenant');

        if (is_string($route) && $route !== '') {
            return $route;
        }

        $header = $request->headers->get('X-Tenant-Id');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
