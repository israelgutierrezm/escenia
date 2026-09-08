<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards tenant-scoped routes: fails the request if no tenant context was
 * resolved. Combined with {@see ResolveTenant} this guarantees that any query
 * to a tenant-owned model inside these routes runs under a valid tenant.
 */
class RequireTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->hasTenant()) {
            abort(
                Response::HTTP_BAD_REQUEST,
                'A tenant context is required. Provide a valid X-Tenant-Id header.'
            );
        }

        return $next($request);
    }
}
