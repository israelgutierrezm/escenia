<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\LiveKit;

use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Media\ValueObjects\RoomSpec;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LiveKit adapter. Access-token issuance is a signed JWT (real and testable
 * without a running server). Rooms are auto-created by LiveKit on first join, so
 * provisioning returns a handle; room teardown / participant removal hit the
 * LiveKit RoomService API best-effort (see technical-debt.md).
 */
final class LiveKitMediaProvider implements MediaProviderContract
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $url,
        private readonly string $host,
        private readonly int $tokenTtlSeconds = 3600,
    ) {}

    public function name(): string
    {
        return 'livekit';
    }

    public function provisionRoom(RoomSpec $spec): RoomHandle
    {
        return new RoomHandle($spec->name, 'livekit');
    }

    public function issueAccessToken(
        RoomHandle $room,
        ParticipantIdentity $identity,
        ParticipantGrants $grants,
    ): AccessToken {
        $now = now();
        $expiresAt = $now->copy()->addSeconds($this->tokenTtlSeconds);

        $token = JWT::encode([
            'iss' => $this->apiKey,
            'sub' => $identity->id,
            'name' => $identity->name,
            'nbf' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'video' => [
                'room' => $room->name,
                'roomJoin' => true,
                'canPublish' => $grants->canPublish,
                'canSubscribe' => $grants->canSubscribe,
                'canPublishData' => $grants->canPublishData,
            ],
        ], $this->apiSecret, 'HS256');

        return new AccessToken(
            token: $token,
            url: $this->url,
            identity: $identity->id,
            room: $room->name,
            expiresAt: $expiresAt,
        );
    }

    public function removeParticipant(RoomHandle $room, ParticipantIdentity $identity): void
    {
        $this->callRoomService('RemoveParticipant', ['room' => $room->name, 'identity' => $identity->id]);
    }

    public function closeRoom(RoomHandle $room): void
    {
        $this->callRoomService('DeleteRoom', ['room' => $room->name]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function callRoomService(string $method, array $body): void
    {
        if ($this->host === '') {
            return; // No server API configured; nothing to call.
        }

        try {
            Http::withToken($this->adminToken())
                ->asJson()
                ->post(rtrim($this->host, '/')."/twirp/livekit.RoomService/{$method}", $body);
        } catch (Throwable $e) {
            // Room teardown is best-effort cleanup; never fail the request over it.
            Log::warning('LiveKit RoomService call failed', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }

    private function adminToken(): string
    {
        $now = now();

        return JWT::encode([
            'iss' => $this->apiKey,
            'nbf' => $now->getTimestamp(),
            'exp' => $now->copy()->addMinutes(10)->getTimestamp(),
            'video' => ['roomAdmin' => true],
        ], $this->apiSecret, 'HS256');
    }
}
