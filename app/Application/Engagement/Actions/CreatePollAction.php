<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Application\Engagement\DTOs\CreatePollData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Engagement\Enums\PollStatus;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollOption;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a poll in draft with its ordered options. A poll is opened for voting
 * separately (guarded transition).
 */
final class CreatePollAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, CreatePollData $data): Poll
    {
        return DB::transaction(function () use ($event, $actor, $data): Poll {
            $poll = Poll::query()->create([
                'event_id' => $event->getKey(),
                'question' => $data->question,
                'status' => PollStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);

            foreach ($data->options as $position => $label) {
                PollOption::query()->create([
                    'poll_id' => $poll->getKey(),
                    'label' => $label,
                    'position' => $position,
                    'votes_count' => 0,
                ]);
            }

            $this->audit->log('engagement.poll.created', actor: $actor, tenant: $event->tenant, auditable: $poll);

            return $poll->load('options');
        });
    }
}
