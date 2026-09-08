<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Application\Engagement\Actions\JoinEventAction;
use App\Application\Engagement\Actions\LeaveEventAction;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendeeSessionResource;
use Illuminate\Http\JsonResponse;

/**
 * Attendee presence: open/refresh a session (also the heartbeat) and close it.
 * Authenticated by the join token via the `attendee` middleware.
 */
class PresenceController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function join(JoinEventAction $action): JsonResponse
    {
        // Idempotent (also the heartbeat): always 200, never 201 on first open.
        return AttendeeSessionResource::make($action->execute($this->context->attendeeOrFail()))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK);
    }

    public function leave(LeaveEventAction $action): JsonResponse
    {
        $session = $action->execute($this->context->attendeeOrFail());

        return response()->json([
            'data' => $session !== null ? AttendeeSessionResource::make($session) : null,
        ]);
    }
}
