<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Domain\Education\Exceptions\AlreadySubmittedException;
use App\Domain\Education\Exceptions\AssessmentNotAvailableException;
use App\Domain\Education\Models\Assessment;
use App\Domain\Education\Models\AssessmentSubmission;
use App\Domain\Education\Support\AssessmentScorer;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * An attendee submits an assessment. It is scored server-side, and — since a
 * pass may complete the course — an `assessment.submitted` event is written to
 * the outbox (ADR-007) so certification is evaluated out of band. One submission
 * per (assessment, attendee); the unique index guards races.
 */
final class TakeAssessmentAction
{
    public function __construct(
        private readonly AssessmentScorer $scorer,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     */
    public function execute(Attendee $attendee, Assessment $assessment, array $answers): AssessmentSubmission
    {
        if (! $assessment->is_published || $assessment->questions()->count() === 0) {
            throw new AssessmentNotAvailableException;
        }

        $scored = $this->scorer->score($assessment, $answers);

        try {
            return DB::transaction(function () use ($attendee, $assessment, $answers, $scored): AssessmentSubmission {
                $submission = AssessmentSubmission::query()->create([
                    'assessment_id' => $assessment->getKey(),
                    'attendee_id' => $attendee->getKey(),
                    'answers' => $answers,
                    'score' => $scored['score'],
                    'passed' => $scored['passed'],
                    'submitted_at' => now(),
                ]);

                OutboxEvent::query()->create([
                    'topic' => 'assessment.submitted',
                    'payload' => ['event_id' => $assessment->event_id, 'attendee_id' => $attendee->getKey()],
                    'available_at' => now(),
                ]);

                return $submission;
            });
        } catch (UniqueConstraintViolationException) {
            throw new AlreadySubmittedException;
        }
    }
}
