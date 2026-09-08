<?php

declare(strict_types=1);

namespace App\Domain\Shared\Scopes;

use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically constrains every query on a tenant-owned model to the tenant
 * resolved for the current request. See ADR-010 (tenant isolation).
 *
 * Behaviour:
 *  - tenant resolved            -> constrain by tenant_id (the normal path).
 *  - unscoped explicitly allowed -> no constraint (trusted system/seed ops).
 *  - running in console          -> no constraint (CLI/queue/tests set context
 *                                   explicitly; HTTP requests set it via
 *                                   middleware inside the request lifecycle).
 *  - otherwise                   -> throw (fail closed).
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->hasTenant()) {
            $builder->where(
                $model->qualifyColumn('tenant_id'),
                $context->id()
            );

            return;
        }

        if ($context->isUnscopedAllowed() || app()->runningInConsole()) {
            return;
        }

        throw new TenantContextMissingException($model::class);
    }
}
