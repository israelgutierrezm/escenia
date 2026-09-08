<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Studio;

use App\Application\Studio\Actions\EndStudioSessionAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Application\Studio\Actions\StartStudioSessionAction;
use App\Domain\Events\Models\Event;
use App\Domain\Studio\Models\Studio;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudioController extends Controller
{
    public function show(Request $request, EnsureStudioAction $ensure, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('view', $studio);

        return $this->ok($studio);
    }

    public function start(Request $request, EnsureStudioAction $ensure, StartStudioSessionAction $start, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('manage', $studio);

        $start->execute($studio, $request->user());

        return $this->ok($studio->refresh());
    }

    public function end(Request $request, EnsureStudioAction $ensure, EndStudioSessionAction $end, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('manage', $studio);

        $session = $studio->currentSession();
        if ($session !== null) {
            $end->execute($session, $request->user());
        }

        return $this->ok($studio->refresh());
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    /**
     * Ensuring/starting/ending a studio is idempotent control, not resource
     * creation — always respond 200 (a freshly-ensured studio would otherwise
     * surface as 201 via the Resource's recently-created behaviour).
     */
    private function ok(Studio $studio): JsonResponse
    {
        return StudioResource::make($studio)->response()->setStatusCode(Response::HTTP_OK);
    }
}
