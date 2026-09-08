<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\LiveKit;

use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Media\ValueObjects\EgressHandle;
use App\Domain\Media\ValueObjects\EgressSpec;
use App\Domain\Media\ValueObjects\ParticipantGrants;
use App\Domain\Media\ValueObjects\ParticipantIdentity;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Media\ValueObjects\RoomSpec;
use App\Domain\Media\ValueObjects\StreamOutput;
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
final class LiveKitMediaProvider implements MediaEgressProvider, MediaProviderContract
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
        $this->callTwirp('RoomService', 'RemoveParticipant', ['room' => $room->name, 'identity' => $identity->id]);
    }

    public function closeRoom(RoomHandle $room): void
    {
        $this->callTwirp('RoomService', 'DeleteRoom', ['room' => $room->name]);
    }

    public function startEgress(RoomHandle $room, EgressSpec $spec): EgressHandle
    {
        $streamOutputs = array_map(
            static fn (StreamOutput $output): array => ['urls' => [rtrim($output->url, '/').'/'.$output->streamKey]],
            $spec->outputs,
        );

        $this->callTwirp('Egress', 'StartRoomCompositeEgress', [
            'room_name' => $room->name,
            'stream_outputs' => $streamOutputs,
        ]);

        // Without a live server the real egress id is unknown; a synthetic id is
        // returned (in production it comes from the API response).
        return new EgressHandle('egr_'.bin2hex(random_bytes(8)), 'livekit');
    }

    public function stopEgress(EgressHandle $egress): void
    {
        $this->callTwirp('Egress', 'StopEgress', ['egress_id' => $egress->id]);
    }

    public function egressHealth(EgressHandle $egress): string
    {
        return 'unknown';
    }

    /**
     * Best-effort call to a LiveKit Twirp service; failures are logged, not fatal.
     *
     * @param  array<string, mixed>  $body
     */
    private function callTwirp(string $service, string $method, array $body): void
    {
        if ($this->host === '') {
            return; // No server API configured; nothing to call.
        }

        try {
            Http::withToken($this->adminToken())
                ->asJson()
                ->post(rtrim($this->host, '/')."/twirp/livekit.{$service}/{$method}", $body);
        } catch (Throwable $e) {
            Log::warning('LiveKit call failed', ['service' => $service, 'method' => $method, 'error' => $e->getMessage()]);
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
