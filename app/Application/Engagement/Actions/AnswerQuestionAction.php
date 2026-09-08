<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Engagement\Enums\QuestionStatus;
use App\Domain\Engagement\Models\Question;
use App\Domain\Identity\Models\User;

/**
 * A host answers a Q&A question, marking it answered. Re-answering an already
 * answered question is allowed (the host edits their answer).
 */
final class AnswerQuestionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Question $question, User $actor, string $answer): Question
    {
        $question->forceFill([
            'answer' => $answer,
            'status' => QuestionStatus::Answered,
            'answered_by' => $actor->getKey(),
            'answered_at' => now(),
        ])->save();

        $this->audit->log('engagement.question.answered', actor: $actor, tenant: $question->tenant, auditable: $question);

        return $question;
    }
}
