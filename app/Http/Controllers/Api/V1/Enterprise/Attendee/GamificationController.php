<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Attendee;

use App\Application\Gamification\LeaderboardService;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Attendee gamification: their points and the event leaderboard.
 */
class GamificationController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(LeaderboardService $leaderboard): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();
        $event = Event::query()->whereKey($attendee->event_id)->firstOrFail();

        return response()->json([
            'data' => [
                'points' => $leaderboard->pointsFor($attendee),
                'leaderboard' => $leaderboard->forEvent($event),
            ],
        ]);
    }
}
