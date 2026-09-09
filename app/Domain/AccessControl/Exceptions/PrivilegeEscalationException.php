<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Exceptions;

use RuntimeException;

/**
 * Thrown when an actor tries to grant access they do not themselves hold, or to
 * touch the owner tier without being an owner. This is the anti-escalation guard
 * for the RBAC management surface. Mapped to HTTP 403.
 */
final class PrivilegeEscalationException extends RuntimeException
{
    public function __construct(string $message = 'You cannot grant access beyond your own.')
    {
        parent::__construct($message);
    }
}
