<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Studio\Models\StudioSession;

/**
 * Issues a media token for the producer/host driving the console. The host is
 * not a StudioParticipant (they never occupy a stage seat), so they connect to
 * the session room under a stable `host-{id}` identity with full grants to
 * monitor every feed and direct the show. The domain never touches the media
 * SDK — identity and grants are resolved here and handed to the provider.
 */
final class IssueHostTokenAction
{
    public function __construct(
        private readonly MediaProviderContract $media,
    ) {}

    public function execute(StudioSession $session, User $host): AccessToken
    {
        $room = new RoomHandle($session->room_ref, $this->media->name());
        $identity = new ParticipantIdentity('host-'.$host->getKey(), $host->name);

        return $this->media->issueAccessToken($room, $identity, ParticipantGrants::full());
    }
}
