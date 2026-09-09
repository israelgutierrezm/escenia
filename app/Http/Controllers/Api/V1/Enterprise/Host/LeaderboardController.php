<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Gamification\LeaderboardService;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    use ResolvesEvent;

    public function index(LeaderboardService $leaderboard, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $model);

        return response()->json(['data' => $leaderboard->forEvent($model)]);
    }
}
