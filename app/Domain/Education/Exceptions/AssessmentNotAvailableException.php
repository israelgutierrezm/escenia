<?php

declare(strict_types=1);

namespace App\Domain\Education\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to take an assessment that is not published (or
 * has no questions). Mapped to HTTP 422.
 */
final class AssessmentNotAvailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This assessment is not available.');
    }
}
