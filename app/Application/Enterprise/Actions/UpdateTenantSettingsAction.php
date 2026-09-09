<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;

/**
 * Updates a tenant's enterprise residency settings (data region / dedicated
 * flag). These record intent on the control plane and are audited; actual
 * region pinning and dedicated-infra provisioning are future work.
 */
final class UpdateTenantSettingsAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, Tenant $tenant, array $attributes): Tenant
    {
        $tenant->fill($attributes)->save();

        $this->audit->log('enterprise.tenant.settings_updated', actor: $actor, tenant: $tenant, auditable: $tenant, context: [
            'changed' => array_keys($attributes),
        ]);

        return $tenant->refresh();
    }
}
