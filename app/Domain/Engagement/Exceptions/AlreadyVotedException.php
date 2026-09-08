<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to vote twice on the same poll. A poll vote is a
 * single, final choice. Mapped to HTTP 409.
 */
final class AlreadyVotedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You have already voted on this poll.');
    }
}
