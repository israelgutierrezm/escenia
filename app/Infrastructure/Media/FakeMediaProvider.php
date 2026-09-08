<?php

declare(strict_types=1);

namespace App\Infrastructure\Media;

use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\EgressHandle;
use App\Domain\Media\ValueObjects\EgressSpec;
use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Media\ValueObjects\RoomSpec;

/**
 * Deterministic, network-free media provider for local development and tests.
 * The test suite never needs Internet (testing-strategy.md).
 */
final class FakeMediaProvider implements MediaEgressProvider, MediaProviderContract
{
    public function name(): string
    {
        return 'fake';
    }

    public function provisionRoom(RoomSpec $spec): RoomHandle
    {
        return new RoomHandle($spec->name, 'fake');
    }

    public function issueAccessToken(
        RoomHandle $room,
        ParticipantIdentity $identity,
        ParticipantGrants $grants,
    ): AccessToken {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'room' => $room->name,
            'id' => $identity->id,
            'pub' => $grants->canPublish,
            'sub' => $grants->canSubscribe,
            'data' => $grants->canPublishData,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return new AccessToken(
            token: 'fake.'.$payload,
            url: 'wss://fake.media.local',
            identity: $identity->id,
            room: $room->name,
            expiresAt: now()->addHour(),
        );
    }

    public function removeParticipant(RoomHandle $room, ParticipantIdentity $identity): void
    {
        // No-op: nothing to talk to.
    }

    public function closeRoom(RoomHandle $room): void
    {
        // No-op.
    }

    public function startEgress(RoomHandle $room, EgressSpec $spec): EgressHandle
    {
        return new EgressHandle('egr_'.substr(md5($room->name.'|'.count($spec->outputs)), 0, 16), 'fake');
    }

    public function stopEgress(EgressHandle $egress): void
    {
        // No-op.
    }

    public function egressHealth(EgressHandle $egress): string
    {
        return 'healthy';
    }
}
