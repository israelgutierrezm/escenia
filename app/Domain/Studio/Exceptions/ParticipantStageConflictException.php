<?php

declare(strict_types=1);

namespace App\Domain\Studio\Exceptions;

use App\Domain\Studio\Enums\ParticipantStage;
use RuntimeException;

/**
 * Thrown when a stage move loses an optimistic-locking race (the participant was
 * moved concurrently). Mapped to HTTP 409.
 */
final class ParticipantStageConflictException extends RuntimeException
{
    public function __construct(
        public readonly ParticipantStage $from,
        public readonly ParticipantStage $to,
    ) {
        parent::__construct(
            "The participant changed stage concurrently; could not move from [{$from->value}] to [{$to->value}]."
        );
    }
}
