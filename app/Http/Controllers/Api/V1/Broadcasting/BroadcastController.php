<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Broadcasting;

use App\Application\Broadcasting\Actions\ReportBroadcastHealthAction;
use App\Application\Broadcasting\Actions\StartBroadcastAction;
use App\Application\Broadcasting\Actions\StopBroadcastAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Domain\Broadcasting\Enums\BroadcastHealth;
use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Events\Models\Event;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcasting\ReportBroadcastHealthRequest;
use App\Http\Requests\Broadcasting\StartBroadcastRequest;
use App\Http\Resources\BroadcastSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BroadcastController extends Controller
{
    public function show(string $event): JsonResponse
    {
        $studio = Studio::query()->where('event_id', $this->resolveEvent($event)->getKey())->first();

        $this->authorize('viewBroadcast', $studio ?? new Studio);

        $session = $studio?->currentSession();
        $broadcast = $session !== null
            ? BroadcastSession::query()->where('studio_session_id', $session->getKey())->latest()->first()
            : null;

        return response()->json([
            'data' => $broadcast !== null
                ? BroadcastSessionResource::make($broadcast->load('destinations'))
                : null,
        ]);
    }

    public function start(StartBroadcastRequest $request, EnsureStudioAction $ensure, StartBroadcastAction $action, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('broadcast', $studio);

        $session = $studio->currentSession();
        abort_if($session === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Start the studio before broadcasting.');

        /** @var list<string> $destinations */
        $destinations = $request->validated('destinations');

        $broadcast = $action->execute($session, $request->user(), $destinations, (bool) $request->validated('record'));

        return BroadcastSessionResource::make($broadcast->load('destinations'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function stop(Request $request, StopBroadcastAction $action, string $broadcast): BroadcastSessionResource
    {
        $model = $this->resolveBroadcast($broadcast);

        $this->authorize('broadcast', $this->studioFor($model));

        return BroadcastSessionResource::make($action->execute($model, $request->user())->load('destinations'));
    }

    public function health(ReportBroadcastHealthRequest $request, ReportBroadcastHealthAction $action, string $broadcast): BroadcastSessionResource
    {
        $model = $this->resolveBroadcast($broadcast);

        $this->authorize('broadcast', $this->studioFor($model));

        $health = BroadcastHealth::from((string) $request->validated('health'));

        return BroadcastSessionResource::make($action->execute($model, $request->user(), $health)->load('destinations'));
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function resolveBroadcast(string $ulid): BroadcastSession
    {
        return BroadcastSession::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function studioFor(BroadcastSession $broadcast): Studio
    {
        $session = StudioSession::query()->whereKey($broadcast->studio_session_id)->firstOrFail();

        return Studio::query()->whereKey($session->studio_id)->firstOrFail();
    }
}
