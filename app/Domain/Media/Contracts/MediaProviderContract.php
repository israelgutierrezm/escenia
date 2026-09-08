<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Media\ValueObjects\RoomSpec;

/**
 * The boundary between the control plane and the media plane (ADR-002 / ADR-008).
 * The domain talks only to this contract and its value objects — never to a
 * media SDK. Studio MVP uses the interactive/room subset; ingress/egress/
 * recording operations arrive with later phases.
 */
interface MediaProviderContract
{
    public function name(): string;

    public function provisionRoom(RoomSpec $spec): RoomHandle;

    public function issueAccessToken(
        RoomHandle $room,
        ParticipantIdentity $identity,
        ParticipantGrants $grants,
    ): AccessToken;

    public function removeParticipant(RoomHandle $room, ParticipantIdentity $identity): void;

    public function closeRoom(RoomHandle $room): void;
}
