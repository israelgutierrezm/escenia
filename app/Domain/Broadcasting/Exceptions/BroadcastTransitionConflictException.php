<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Exceptions;

use App\Domain\Broadcasting\Enums\BroadcastStatus;
use RuntimeException;

/**
 * Thrown when a broadcast transition loses an optimistic-locking race. Mapped to
 * HTTP 409.
 */
final class BroadcastTransitionConflictException extends RuntimeException
{
    public function __construct(
        public readonly BroadcastStatus $from,
        public readonly BroadcastStatus $to,
    ) {
        parent::__construct(
            "The broadcast changed state concurrently; could not transition from [{$from->value}] to [{$to->value}]."
        );
    }
}
