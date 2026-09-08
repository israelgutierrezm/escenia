<?php

declare(strict_types=1);

namespace App\Domain\Events\Exceptions;

use App\Domain\Events\Enums\EventStatus;
use RuntimeException;

/**
 * Thrown when a lifecycle transition loses an optimistic-locking race: the event
 * was moved to another state concurrently between the guard check and the write
 * (see ADR-017). Mapped to HTTP 409 by the API exception mapper.
 */
final class EventTransitionConflictException extends RuntimeException
{
    public function __construct(
        public readonly EventStatus $from,
        public readonly EventStatus $to,
    ) {
        parent::__construct(
            "The event changed state concurrently; could not transition from [{$from->value}] to [{$to->value}]."
        );
    }
}
