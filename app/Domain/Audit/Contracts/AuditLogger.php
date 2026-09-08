<?php

declare(strict_types=1);

namespace App\Domain\Audit\Contracts;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Records security-relevant actions to the immutable audit trail. Implementations
 * MUST NOT persist secrets in the context payload.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $action,
        ?User $actor = null,
        ?Tenant $tenant = null,
        ?Model $auditable = null,
        array $context = [],
    ): AuditLog;
}
