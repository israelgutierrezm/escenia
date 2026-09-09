<?php

declare(strict_types=1);

namespace App\Domain\Education\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to submit an assessment they have already
 * completed. Mapped to HTTP 409.
 */
final class AlreadySubmittedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You have already submitted this assessment.');
    }
}
