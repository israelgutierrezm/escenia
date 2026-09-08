<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Studio\Models\StudioParticipant;
use App\Domain\Studio\ParticipantGrantPolicy;

/**
 * Issues a short-lived media access token for a participant, with grants derived
 * from their current role + stage. The domain never touches the media SDK.
 */
final class IssueParticipantTokenAction
{
    public function __construct(
        private readonly MediaProviderContract $media,
    ) {}

    public function execute(StudioParticipant $participant): AccessToken
    {
        $session = $participant->session()->firstOrFail();

        $room = new RoomHandle($session->room_ref, $this->media->name());
        $identity = new ParticipantIdentity($participant->identity, $participant->name);
        $grants = ParticipantGrantPolicy::for($participant->role, $participant->stage);

        return $this->media->issueAccessToken($room, $identity, $grants);
    }
}
