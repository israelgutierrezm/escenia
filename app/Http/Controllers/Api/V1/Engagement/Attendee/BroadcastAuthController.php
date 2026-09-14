<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Presence-channel auth for attendees. Attendees are token-authenticated (no
 * Sanctum session), so they cannot use the default `/broadcasting/auth`. This
 * signs the Pusher-protocol presence payload for `presence-viewers.{ulid}` of
 * the attendee's OWN event only, tagging the member as role `attendee` so the
 * producer console can count live viewers.
 */
class BroadcastAuthController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();
        $eventUlid = $attendee->event->ulid;

        $socketId = (string) $request->input('socket_id');
        $channelName = (string) $request->input('channel_name');

        // An attendee may only join the viewer presence channel of their own event.
        if ($channelName !== "presence-viewers.{$eventUlid}") {
            return response()->json(['message' => 'Canal no permitido.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $channelData = json_encode([
            'user_id' => $attendee->ulid,
            'user_info' => ['id' => $attendee->ulid, 'name' => $attendee->name, 'role' => 'attendee'],
        ], JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $app */
        $app = (array) config('reverb.apps.apps.0');
        $key = (string) ($app['key'] ?? '');
        $secret = (string) ($app['secret'] ?? '');

        $signature = hash_hmac('sha256', $socketId.':'.$channelName.':'.$channelData, $secret);

        return response()->json([
            'auth' => $key.':'.$signature,
            'channel_data' => $channelData,
        ]);
    }
}
