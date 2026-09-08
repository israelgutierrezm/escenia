<?php

declare(strict_types=1);

namespace App\Domain\Studio\Events;

use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Models\StudioParticipant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a participant moves between studio stages. Application realtime
 * (Reverb) will broadcast this to the studio UI in a later phase (realtime.md).
 */
final class ParticipantStageChanged
{
    use Dispatchable;

    public function __construct(
        public readonly StudioParticipant $participant,
        public readonly ParticipantStage $from,
        public readonly ParticipantStage $to,
    ) {}
}
