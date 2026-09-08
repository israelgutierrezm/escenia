<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Engagement\Models\Question;
use App\Domain\Engagement\Models\QuestionVote;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Support\Facades\DB;

/**
 * An attendee upvotes a question. Idempotent: one vote per (question, attendee),
 * and the denormalized counter is only incremented on the first vote.
 */
final class VoteQuestionAction
{
    public function execute(Attendee $attendee, Question $question): Question
    {
        return DB::transaction(function () use ($attendee, $question): Question {
            $vote = QuestionVote::query()->firstOrCreate([
                'question_id' => $question->getKey(),
                'attendee_id' => $attendee->getKey(),
            ]);

            if ($vote->wasRecentlyCreated) {
                $question->increment('votes_count');
            }

            return $question->refresh();
        });
    }
}
