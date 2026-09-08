<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-owned model is queried in an HTTP request lifecycle
 * without a resolved tenant context. Fails closed: we never silently return
 * an unscoped (cross-tenant) result set.
 */
final class TenantContextMissingException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(
            "Attempted to query tenant-owned model [{$model}] without a resolved tenant context."
        );
    }
}
