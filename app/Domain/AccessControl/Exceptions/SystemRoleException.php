<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation targets a system role (owner/admin/member) that may
 * not be edited, deleted, or assigned as if it were a custom role. Mapped to
 * HTTP 422.
 */
final class SystemRoleException extends RuntimeException
{
    public function __construct(string $message = 'System roles cannot be modified.')
    {
        parent::__construct($message);
    }
}
