<?php

declare(strict_types=1);

namespace App\Domain\Studio;

use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Studio\Enums\ParticipantRole;
use App\Domain\Studio\Enums\ParticipantStage;

/**
 * Maps a participant's role + stage to the media capabilities they are granted
 * when a token is issued. Keeps this policy out of the media adapters and the
 * generic ParticipantGrants value object.
 */
final class ParticipantGrantPolicy
{
    public static function for(ParticipantRole $role, ParticipantStage $stage): ParticipantGrants
    {
        // No seat in the room -> no media grants (should not receive a token).
        if (! $stage->isInRoom()) {
            return new ParticipantGrants(canPublish: false, canSubscribe: false, canPublishData: false);
        }

        // Hosts and producers always have full control.
        if ($role === ParticipantRole::Host || $role === ParticipantRole::Producer) {
            return ParticipantGrants::full();
        }

        // Speakers/guests can publish and subscribe while in the room; data
        // channels open once they are on stage or backstage.
        $canPublishData = $stage === ParticipantStage::Stage || $stage === ParticipantStage::Backstage;

        return new ParticipantGrants(canPublish: true, canSubscribe: true, canPublishData: $canPublishData);
    }
}
