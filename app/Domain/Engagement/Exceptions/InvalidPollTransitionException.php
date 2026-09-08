<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Exceptions;

use App\Domain\Engagement\Enums\PollStatus;
use RuntimeException;

/**
 * Thrown when a poll lifecycle transition is not allowed. Mapped to HTTP 422.
 */
final class InvalidPollTransitionException extends RuntimeException
{
    public function __construct(
        public readonly PollStatus $from,
        public readonly PollStatus $to,
    ) {
        parent::__construct("Cannot transition a poll from [{$from->value}] to [{$to->value}].");
    }
}
