<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\Scopes\TenantScope;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a tenant. Applies the {@see TenantScope} global
 * scope and auto-fills tenant_id from the resolved {@see TenantContext} on
 * creation, so application code never has to remember to set it.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            if (! empty($model->tenant_id)) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->hasTenant()) {
                $model->tenant_id = $context->id();
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
