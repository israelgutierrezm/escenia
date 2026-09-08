<?php

declare(strict_types=1);

namespace App\Domain\Events\Exceptions;

use App\Domain\Events\Enums\Capability;
use RuntimeException;

/**
 * Thrown when enabling a capability the tenant's plan does not entitle
 * (see ADR-013 / ADR-016). Mapped to HTTP 403 by the API exception mapper.
 */
final class CapabilityNotEntitledException extends RuntimeException
{
    public function __construct(
        public readonly Capability $capability,
    ) {
        parent::__construct("The current plan does not include the [{$capability->value}] capability.");
    }
}
