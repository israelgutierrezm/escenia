<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Context;

use App\Domain\Tenancy\Exceptions\TenantContextMissingException;
use App\Domain\Tenancy\Models\Tenant;
use Closure;

/**
 * Request-scoped holder of the currently resolved tenant.
 *
 * The tenant is ALWAYS resolved server-side from the authenticated user's
 * verified memberships (see ResolveTenant middleware / ADR-010). It is never
 * trusted from a raw client-supplied identifier. Bound as a scoped singleton
 * so it resets per request (Octane-safe).
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $unscoped = false;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function ulid(): ?string
    {
        return $this->tenant?->ulid;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Return the resolved tenant or fail. Use on routes guarded by RequireTenant
     * where the tenant is guaranteed to be present.
     */
    public function tenantOrFail(): Tenant
    {
        if ($this->tenant === null) {
            throw new TenantContextMissingException(self::class);
        }

        return $this->tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function isUnscopedAllowed(): bool
    {
        return $this->unscoped;
    }

    /**
     * Run a callback with tenant scoping disabled. Reserved for trusted
     * system/seed operations that legitimately cross tenant boundaries.
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    public function allowUnscoped(Closure $callback): mixed
    {
        $previous = $this->unscoped;
        $this->unscoped = true;

        try {
            return $callback();
        } finally {
            $this->unscoped = $previous;
        }
    }

    /**
     * Run a callback within the scope of the given tenant.
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    public function runFor(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
