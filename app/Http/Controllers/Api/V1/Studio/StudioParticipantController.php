<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Studio;

use App\Application\Studio\Actions\AdmitParticipantAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Application\Studio\Actions\IssueParticipantTokenAction;
use App\Application\Studio\Actions\MoveParticipantAction;
use App\Application\Studio\Actions\StartStudioSessionAction;
use App\Application\Studio\DTOs\AdmitParticipantData;
use App\Domain\Events\Models\Event;
use App\Domain\Studio\Enums\ParticipantRole;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioParticipant;
use App\Domain\Studio\Models\StudioSession;
use App\Http\Concerns\FormatsAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\AdmitParticipantRequest;
use App\Http\Requests\Studio\MoveParticipantRequest;
use App\Http\Resources\StudioParticipantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudioParticipantController extends Controller
{
    use FormatsAccessToken;

    public function index(string $event): AnonymousResourceCollection
    {
        $studio = Studio::query()->where('event_id', $this->resolveEvent($event)->getKey())->first();

        $this->authorize('view', $studio ?? new Studio);

        $session = $studio?->currentSession();
        $participants = $session !== null
            ? $session->participants()->orderBy('created_at')->get()
            : collect();

        return StudioParticipantResource::collection($participants);
    }

    public function store(
        AdmitParticipantRequest $request,
        EnsureStudioAction $ensure,
        StartStudioSessionAction $start,
        AdmitParticipantAction $admit,
        string $event,
    ): JsonResponse {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('manage', $studio);

        // Admitting implies a live session; start one if needed.
        $session = $studio->currentSession() ?? $start->execute($studio, $request->user());

        $result = $admit->execute($session, $request->user(), new AdmitParticipantData(
            name: (string) $request->validated('name'),
            role: ParticipantRole::from((string) $request->validated('role')),
        ));

        return response()->json([
            'data' => [
                'participant' => StudioParticipantResource::make($result['participant']),
                'access' => $this->accessTokenArray($result['token']),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function move(MoveParticipantRequest $request, MoveParticipantAction $move, string $participant): StudioParticipantResource
    {
        $model = $this->resolveParticipant($participant);

        $this->authorize('manage', $this->studioFor($model));

        $target = ParticipantStage::from((string) $request->validated('stage'));

        return StudioParticipantResource::make($move->execute($model, $request->user(), $target));
    }

    public function token(Request $request, IssueParticipantTokenAction $issue, string $participant): JsonResponse
    {
        $model = $this->resolveParticipant($participant);

        $this->authorize('view', $this->studioFor($model));

        return response()->json([
            'data' => ['access' => $this->accessTokenArray($issue->execute($model))],
        ]);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function resolveParticipant(string $ulid): StudioParticipant
    {
        return StudioParticipant::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function studioFor(StudioParticipant $participant): Studio
    {
        $session = StudioSession::query()->whereKey($participant->studio_session_id)->firstOrFail();

        return Studio::query()->whereKey($session->studio_id)->firstOrFail();
    }
}
