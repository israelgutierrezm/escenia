<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Exceptions;

use App\Domain\Broadcasting\Enums\BroadcastStatus;
use RuntimeException;

/**
 * Thrown when a broadcast lifecycle transition is not allowed (ADR-021).
 * Mapped to HTTP 422.
 */
final class InvalidBroadcastTransitionException extends RuntimeException
{
    public function __construct(
        public readonly BroadcastStatus $from,
        public readonly BroadcastStatus $to,
    ) {
        parent::__construct("Cannot transition a broadcast from [{$from->value}] to [{$to->value}].");
    }
}
