<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Broadcasting auth for attendees. Attendees are token-authenticated (no Sanctum
 * session), so they cannot use the default `/broadcasting/auth`. This signs the
 * Pusher-protocol auth for the two channels an attendee may join — the viewer
 * PRESENCE channel of their own event, and their own PRIVATE notification
 * channel — and nothing else.
 */
class BroadcastAuthController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();

        $socketId = (string) $request->input('socket_id');
        $channelName = (string) $request->input('channel_name');

        // Presence: the viewer count of the attendee's own event.
        if ($channelName === "presence-viewers.{$attendee->event->ulid}") {
            $channelData = json_encode([
                'user_id' => $attendee->ulid,
                'user_info' => ['id' => $attendee->ulid, 'name' => $attendee->name, 'role' => 'attendee'],
            ], JSON_THROW_ON_ERROR);

            return response()->json([
                'auth' => $this->sign($socketId.':'.$channelName.':'.$channelData),
                'channel_data' => $channelData,
            ]);
        }

        // Private: the attendee's own notification channel.
        if ($channelName === "private-attendee.{$attendee->ulid}") {
            return response()->json(['auth' => $this->sign($socketId.':'.$channelName)]);
        }

        return response()->json(['message' => 'Canal no permitido.'], JsonResponse::HTTP_FORBIDDEN);
    }

    private function sign(string $payload): string
    {
        /** @var array<string, mixed> $app */
        $app = (array) config('reverb.apps.apps.0');

        return (string) ($app['key'] ?? '').':'.hash_hmac('sha256', $payload, (string) ($app['secret'] ?? ''));
    }
}
