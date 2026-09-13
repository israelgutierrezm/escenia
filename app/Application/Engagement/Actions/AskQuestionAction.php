<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Application\Engagement\Events\QuestionActivity;
use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Enums\QuestionStatus;
use App\Domain\Engagement\Models\Question;
use App\Domain\Registration\Models\Attendee;

/**
 * An attendee asks a Q&A question. Starts open with zero votes.
 */
final class AskQuestionAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee, string $body): Question
    {
        $question = Question::query()->create([
            'event_id' => $attendee->event_id,
            'attendee_id' => $attendee->getKey(),
            'author_name' => $attendee->name,
            'body' => $body,
            'status' => QuestionStatus::Open,
            'votes_count' => 0,
        ]);

        $event = $attendee->event()->firstOrFail();
        $this->analytics->record(AnalyticsEventName::EngagementQuestionAsked, $event, $attendee);

        event(new QuestionActivity($event->ulid));

        return $question;
    }
}
