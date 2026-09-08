<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Exceptions;

use App\Domain\Engagement\Enums\PollStatus;
use RuntimeException;

/**
 * Thrown when a poll transition loses an optimistic-locking race. Mapped to
 * HTTP 409.
 */
final class PollTransitionConflictException extends RuntimeException
{
    public function __construct(
        public readonly PollStatus $from,
        public readonly PollStatus $to,
    ) {
        parent::__construct(
            "The poll changed state concurrently; could not transition from [{$from->value}] to [{$to->value}]."
        );
    }
}
