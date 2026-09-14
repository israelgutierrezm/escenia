<?php

declare(strict_types=1);

namespace App\Domain\Networking\Exceptions;

use App\Domain\Networking\Enums\MeetingStatus;
use RuntimeException;

/**
 * Thrown when a meeting status change is not allowed by the lifecycle (or was
 * already changed concurrently). Mapped to 422.
 */
final class InvalidMeetingTransitionException extends RuntimeException
{
    public function __construct(
        public readonly MeetingStatus $from,
        public readonly MeetingStatus $to,
    ) {
        parent::__construct("Cannot move a meeting from [{$from->value}] to [{$to->value}].");
    }
}
