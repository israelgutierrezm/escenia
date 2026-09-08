<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Tenancy\Models\Tenant;

/**
 * Resolves what a tenant is entitled to from its plan (features + limits).
 * The rest of the system asks this contract instead of branching on a plan key.
 */
interface EntitlementResolver
{
    public function allows(Tenant $tenant, string $feature): bool;

    public function limit(Tenant $tenant, string $key): ?int;

    /**
     * @return list<string>
     */
    public function features(Tenant $tenant): array;
}
