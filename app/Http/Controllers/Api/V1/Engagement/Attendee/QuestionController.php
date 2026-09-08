<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Application\Engagement\Actions\AskQuestionAction;
use App\Application\Engagement\Actions\VoteQuestionAction;
use App\Domain\Engagement\Models\Question;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\AskQuestionRequest;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of Q&A: read the queue, ask, and upvote.
 */
class QuestionController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $questions = Question::query()
            ->where('event_id', $attendee->event_id)
            ->orderByDesc('votes_count')
            ->orderByDesc('id')
            ->paginate(50);

        return QuestionResource::collection($questions);
    }

    public function store(AskQuestionRequest $request, AskQuestionAction $action): JsonResponse
    {
        $question = $action->execute($this->context->attendeeOrFail(), (string) $request->validated('body'));

        return QuestionResource::make($question)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function vote(VoteQuestionAction $action, string $question): QuestionResource
    {
        $attendee = $this->context->attendeeOrFail();

        $model = Question::query()
            ->where('ulid', $question)
            ->where('event_id', $attendee->event_id)
            ->firstOrFail();

        return QuestionResource::make($action->execute($attendee, $model));
    }
}
