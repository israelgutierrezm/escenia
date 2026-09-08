<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Host;

use App\Application\Engagement\Actions\CreatePollAction;
use App\Application\Engagement\Actions\TransitionPollAction;
use App\Application\Engagement\DTOs\CreatePollData;
use App\Domain\Engagement\Enums\PollStatus;
use App\Domain\Engagement\Models\Poll;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\CreatePollRequest;
use App\Http\Resources\PollResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host side of polls: list, create (draft), and run the lifecycle (open/close).
 */
class PollController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $polls = Poll::query()
            ->where('event_id', $model->getKey())
            ->with('options')
            ->latest('id')
            ->paginate(50);

        return PollResource::collection($polls);
    }

    public function store(CreatePollRequest $request, CreatePollAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEngagement', $model);

        $poll = $action->execute($model, $request->user(), CreatePollData::fromArray($request->validated()));

        return PollResource::make($poll)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function open(Request $request, TransitionPollAction $action, string $poll): PollResource
    {
        return $this->transition($request, $action, $poll, PollStatus::Open);
    }

    public function close(Request $request, TransitionPollAction $action, string $poll): PollResource
    {
        return $this->transition($request, $action, $poll, PollStatus::Closed);
    }

    private function transition(Request $request, TransitionPollAction $action, string $poll, PollStatus $to): PollResource
    {
        $model = Poll::query()->where('ulid', $poll)->firstOrFail();

        $this->authorize('manageEngagement', $model->event()->firstOrFail());

        return PollResource::make($action->execute($model, $request->user(), $to));
    }
}
