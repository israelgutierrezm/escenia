<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to vote on a poll that is not open. Mapped to
 * HTTP 422.
 */
final class PollNotOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This poll is not open for voting.');
    }
}
