<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Application\Education\DTOs\SaveAssessmentData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Education\Models\Assessment;
use App\Domain\Education\Models\AssessmentQuestion;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the event's assessment and replaces its questions. One
 * assessment per event.
 */
final class SaveAssessmentAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, SaveAssessmentData $data): Assessment
    {
        return DB::transaction(function () use ($event, $actor, $data): Assessment {
            $assessment = Assessment::query()->updateOrCreate(
                ['event_id' => $event->getKey()],
                [
                    'title' => $data->title,
                    'passing_score' => $data->passingScore,
                    'is_published' => $data->isPublished,
                    'created_by' => $actor->getKey(),
                ],
            );

            $assessment->questions()->delete();

            foreach ($data->questions as $position => $question) {
                AssessmentQuestion::query()->create([
                    'assessment_id' => $assessment->getKey(),
                    'position' => $position,
                    'prompt' => $question['prompt'],
                    'type' => $question['type'],
                    'points' => max(1, $question['points']),
                    'options' => $question['options'],
                ]);
            }

            $this->audit->log('education.assessment.saved', actor: $actor, tenant: $event->tenant, auditable: $assessment, context: [
                'published' => $data->isPublished,
            ]);

            return $assessment->load('questions');
        });
    }
}
