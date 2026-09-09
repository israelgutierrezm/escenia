<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education\Attendee;

use App\Application\Education\Actions\TakeAssessmentAction;
use App\Domain\Education\Models\Assessment;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Education\TakeAssessmentRequest;
use App\Http\Resources\AssessmentSubmissionResource;
use App\Http\Resources\AttendeeAssessmentResource;
use Illuminate\Http\JsonResponse;

/**
 * Attendee side of assessments: view the published assessment (without the
 * answer key) and submit it.
 */
class AssessmentController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function show(): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();

        $assessment = $this->publishedAssessment($attendee->event_id);

        return response()->json([
            'data' => $assessment !== null ? AttendeeAssessmentResource::make($assessment) : null,
        ]);
    }

    public function submit(TakeAssessmentRequest $request, TakeAssessmentAction $action): AssessmentSubmissionResource
    {
        $attendee = $this->context->attendeeOrFail();

        $assessment = $this->publishedAssessment($attendee->event_id) ?? abort(404);

        /** @var array<string, mixed> $answers */
        $answers = (array) $request->validated('answers');

        return AssessmentSubmissionResource::make($action->execute($attendee, $assessment, $answers));
    }

    private function publishedAssessment(int $eventId): ?Assessment
    {
        return Assessment::query()
            ->where('event_id', $eventId)
            ->where('is_published', true)
            ->with('questions')
            ->latest('id')
            ->first();
    }
}
