<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\SettingCatalog;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;

/**
 * The read façade for configuration. Callers ask for a catalog key and get the
 * effective value (tenant → system → config default). Provider selectors resolve
 * their configuration through here instead of config() directly, so an operator
 * can reconfigure integrations from within the system (ADR-033).
 */
final class Settings
{
    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * The effective value for a key. Unknown keys return the given fallback.
     */
    public function get(string $key, mixed $fallback = null, ?Tenant $tenant = null): mixed
    {
        $definition = SettingCatalog::find($key);

        if ($definition === null) {
            return $fallback;
        }

        $value = $this->repository->resolve($definition, $tenant);

        return $value ?? $fallback;
    }

    /**
     * Resolve a key for the currently-active tenant (if any), honouring tenant
     * overrides. Safe to call outside a tenant context (falls back to system).
     */
    public function forCurrentTenant(string $key, mixed $fallback = null): mixed
    {
        return $this->get($key, $fallback, $this->tenantContext->tenant());
    }
}
