<?php

declare(strict_types=1);

namespace App\Domain\Studio\Exceptions;

use App\Domain\Studio\Enums\ParticipantStage;
use RuntimeException;

/**
 * Thrown when a participant stage transition is not allowed by the lifecycle
 * (see ADR-019). Mapped to HTTP 422.
 */
final class InvalidParticipantStageTransitionException extends RuntimeException
{
    public function __construct(
        public readonly ParticipantStage $from,
        public readonly ParticipantStage $to,
    ) {
        parent::__construct("Cannot move a participant from [{$from->value}] to [{$to->value}].");
    }
}
