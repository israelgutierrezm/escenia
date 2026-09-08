<?php

declare(strict_types=1);

namespace App\Domain\Registration\Exceptions;

use RuntimeException;

/**
 * Thrown when someone tries to register for an event whose registration form is
 * closed (or absent). Mapped to HTTP 422.
 */
final class RegistrationClosedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Registration for this event is not open.');
    }
}
