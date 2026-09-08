<?php

declare(strict_types=1);

namespace App\Domain\Events\Exceptions;

use App\Domain\Events\Enums\EventStatus;
use RuntimeException;

/**
 * Thrown when an event lifecycle transition is not allowed by the state machine
 * (see ADR-017). Mapped to HTTP 422 by the API exception mapper.
 */
final class InvalidEventTransitionException extends RuntimeException
{
    public function __construct(
        public readonly EventStatus $from,
        public readonly EventStatus $to,
    ) {
        parent::__construct("Cannot transition an event from [{$from->value}] to [{$to->value}].");
    }
}
