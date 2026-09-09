<?php

declare(strict_types=1);

namespace App\Domain\Agenda\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to register for a session that has reached its
 * capacity. Mapped to HTTP 422.
 */
final class SessionFullException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This session is full.');
    }
}
