<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Application\Engagement\Actions\VotePollAction;
use App\Domain\Engagement\Enums\PollStatus;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\VotePollRequest;
use App\Http\Resources\PollResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of polls: see the open polls and cast a vote.
 */
class PollController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $polls = Poll::query()
            ->where('event_id', $attendee->event_id)
            ->whereIn('status', [PollStatus::Open->value, PollStatus::Closed->value])
            ->with('options')
            ->latest('id')
            ->paginate(50);

        return PollResource::collection($polls);
    }

    public function vote(VotePollRequest $request, VotePollAction $action, string $poll): PollResource
    {
        $attendee = $this->context->attendeeOrFail();

        $model = Poll::query()
            ->where('ulid', $poll)
            ->where('event_id', $attendee->event_id)
            ->firstOrFail();

        $option = PollOption::query()
            ->where('ulid', (string) $request->validated('option_id'))
            ->where('poll_id', $model->getKey())
            ->firstOrFail();

        return PollResource::make($action->execute($attendee, $model, $option));
    }
}
