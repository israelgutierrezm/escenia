<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Engagement\Enums\PollStatus;
use App\Domain\Engagement\Exceptions\InvalidPollTransitionException;
use App\Domain\Engagement\Exceptions\PollTransitionConflictException;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guarded, optimistically-locked poll lifecycle transition (draft → open →
 * closed), mirroring the event and broadcast state machines: an illegal
 * transition is 422, a lost compare-and-swap race is 409.
 */
final class TransitionPollAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Poll $poll, User $actor, PollStatus $to): Poll
    {
        $from = $poll->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidPollTransitionException($from, $to);
        }

        return DB::transaction(function () use ($poll, $actor, $from, $to): Poll {
            $applied = Poll::query()
                ->whereKey($poll->getKey())
                ->where('status', $from->value)
                ->update(['status' => $to->value]);

            if ($applied === 0) {
                throw new PollTransitionConflictException($from, $to);
            }

            $poll->refresh();

            $this->audit->log('engagement.poll.transitioned', actor: $actor, tenant: $poll->tenant, auditable: $poll, context: [
                'from' => $from->value,
                'to' => $to->value,
            ]);

            return $poll->load('options');
        });
    }
}
