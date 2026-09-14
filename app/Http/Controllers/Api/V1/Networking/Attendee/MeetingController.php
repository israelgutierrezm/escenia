<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Networking\Attendee;

use App\Application\Networking\Actions\CancelMeetingAction;
use App\Application\Networking\Actions\ProposeMeetingAction;
use App\Application\Networking\Actions\RespondToMeetingAction;
use App\Application\Networking\DTOs\ProposeMeetingData;
use App\Domain\Networking\Models\Meeting;
use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Registration\Models\Attendee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Networking\ProposeMeetingRequest;
use App\Http\Resources\MeetingResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Attendee 1:1 meetings: list mine, propose one, respond to those addressed to
 * me, and cancel (as either party). Scoped to my event and my attendee id.
 */
class MeetingController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $me = $this->context->attendeeOrFail();

        $meetings = Meeting::query()
            ->where('event_id', $me->event_id)
            ->where(fn (Builder $q) => $q->where('proposer_id', $me->getKey())->orWhere('invitee_id', $me->getKey()))
            ->with(['proposer', 'invitee'])
            ->orderBy('scheduled_at')
            ->get();

        return MeetingResource::collection($meetings);
    }

    public function store(ProposeMeetingRequest $request, ProposeMeetingAction $action): JsonResponse
    {
        $me = $this->context->attendeeOrFail();
        $invitee = $this->resolveOther($me, (string) $request->validated('attendee_id'));

        $topic = $request->validated('topic');
        $data = new ProposeMeetingData(
            scheduledAt: Carbon::parse((string) $request->validated('scheduled_at')),
            durationMinutes: (int) $request->validated('duration_minutes'),
            topic: $topic !== null ? (string) $topic : null,
        );

        $meeting = $action->execute($me, $invitee, $data);

        return MeetingResource::make($meeting->load(['proposer', 'invitee']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function accept(RespondToMeetingAction $action, string $meeting): MeetingResource
    {
        return $this->respond($action, $meeting, true);
    }

    public function decline(RespondToMeetingAction $action, string $meeting): MeetingResource
    {
        return $this->respond($action, $meeting, false);
    }

    public function cancel(CancelMeetingAction $action, string $meeting): MeetingResource
    {
        $me = $this->context->attendeeOrFail();

        $model = Meeting::query()
            ->where('ulid', $meeting)
            ->where('event_id', $me->event_id)
            ->where(fn (Builder $q) => $q->where('proposer_id', $me->getKey())->orWhere('invitee_id', $me->getKey()))
            ->firstOrFail();

        return MeetingResource::make($action->execute($model, $me)->load(['proposer', 'invitee']));
    }

    private function respond(RespondToMeetingAction $action, string $meetingUlid, bool $accept): MeetingResource
    {
        $me = $this->context->attendeeOrFail();

        $model = Meeting::query()
            ->where('ulid', $meetingUlid)
            ->where('event_id', $me->event_id)
            ->where('invitee_id', $me->getKey())
            ->firstOrFail();

        return MeetingResource::make($action->execute($model, $accept)->load(['proposer', 'invitee']));
    }

    private function resolveOther(Attendee $me, string $ulid): Attendee
    {
        return Attendee::query()
            ->where('ulid', $ulid)
            ->where('event_id', $me->event_id)
            ->firstOrFail();
    }
}
