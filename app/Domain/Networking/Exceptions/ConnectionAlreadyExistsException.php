<?php

declare(strict_types=1);

namespace App\Domain\Networking\Exceptions;

use RuntimeException;

/**
 * Thrown when a connection between the two attendees already exists (in either
 * direction), whether pending or accepted. Mapped to 409.
 */
final class ConnectionAlreadyExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A connection between these attendees already exists.');
    }
}
