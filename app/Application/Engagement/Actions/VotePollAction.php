<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Enums\PollStatus;
use App\Domain\Engagement\Exceptions\AlreadyVotedException;
use App\Domain\Engagement\Exceptions\PollNotOpenException;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use App\Domain\Engagement\Models\PollVote;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * An attendee casts a single, final vote on an open poll. The unique
 * (poll, attendee) index is the source of truth against double-voting races: a
 * violation becomes a 409 rather than a duplicate row.
 */
final class VotePollAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee, Poll $poll, PollOption $option): Poll
    {
        if ($poll->status !== PollStatus::Open) {
            throw new PollNotOpenException;
        }

        try {
            $result = DB::transaction(function () use ($attendee, $poll, $option): Poll {
                PollVote::query()->create([
                    'poll_id' => $poll->getKey(),
                    'poll_option_id' => $option->getKey(),
                    'attendee_id' => $attendee->getKey(),
                ]);

                $option->increment('votes_count');

                return $poll->load('options');
            });
        } catch (UniqueConstraintViolationException) {
            throw new AlreadyVotedException;
        }

        $this->analytics->record(
            AnalyticsEventName::EngagementPollVoted,
            $attendee->event()->firstOrFail(),
            $attendee,
            ['poll' => $poll->ulid, 'option' => $option->ulid],
        );

        return $result;
    }
}
