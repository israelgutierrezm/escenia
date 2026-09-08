<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Application\Analytics\EventAnalyticsService;
use App\Domain\Events\Models\Event;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Host-facing analytics reporting for an event. Read-only; every endpoint is
 * gated by `analytics.view` and derives its numbers from the analytics plane.
 */
class AnalyticsController extends Controller
{
    use ResolvesEvent;

    public function __construct(
        private readonly EventAnalyticsService $analytics,
    ) {}

    public function summary(string $event): JsonResponse
    {
        $model = $this->authorizedEvent($event);

        return response()->json(['data' => $this->analytics->summary($model)]);
    }

    public function attendance(string $event): JsonResponse
    {
        $model = $this->authorizedEvent($event);

        return response()->json(['data' => $this->analytics->attendanceTimeline($model)]);
    }

    public function engagement(string $event): JsonResponse
    {
        $model = $this->authorizedEvent($event);

        return response()->json(['data' => $this->analytics->engagement($model)]);
    }

    public function attribution(string $event): JsonResponse
    {
        $model = $this->authorizedEvent($event);

        return response()->json(['data' => $this->analytics->attribution($model)]);
    }

    private function authorizedEvent(string $ulid): Event
    {
        $event = $this->resolveEvent($ulid);
        $this->authorize('viewAnalytics', $event);

        return $event;
    }
}
