<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Domain\Billing\Contracts\EntitlementResolver;
use App\Domain\Billing\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;

final class PlanEntitlementResolver implements EntitlementResolver
{
    public function allows(Tenant $tenant, string $feature): bool
    {
        return in_array($feature, $this->features($tenant), true);
    }

    public function limit(Tenant $tenant, string $key): ?int
    {
        $limits = $this->planFor($tenant)?->limits() ?? [];
        $value = $limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * @return list<string>
     */
    public function features(Tenant $tenant): array
    {
        return $this->planFor($tenant)?->features() ?? [];
    }

    private function planFor(Tenant $tenant): ?Plan
    {
        return $tenant->plan()->first()
            ?? Plan::query()->where('key', config('escenia.default_plan'))->first();
    }
}
