<?php

declare(strict_types=1);

namespace App\Application\Events\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Exceptions\EventTransitionConflictException;
use App\Domain\Events\Exceptions\InvalidEventTransitionException;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a guarded lifecycle transition (ADR-017):
 *  - rejects illegal moves up front (InvalidEventTransitionException -> 422);
 *  - applies the change with optimistic locking — the UPDATE only lands if the
 *    row is still in the expected source state, so a concurrent transition that
 *    already moved the event loses the race (EventTransitionConflictException
 *    -> 409) instead of silently double-transitioning;
 *  - stamps actual start/end times, emits a domain event and audits.
 */
final class TransitionEventAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, EventStatus $target): Event
    {
        $from = $event->status;

        // Fast path: reject a transition that is illegal from the known state
        // without opening a transaction.
        if (! $from->canTransitionTo($target)) {
            throw new InvalidEventTransitionException($from, $target);
        }

        return DB::transaction(function () use ($event, $actor, $from, $target): Event {
            $updates = ['status' => $target->value];

            // Live/Ended are each entered at most once by the state machine, so
            // stamping unconditionally here is safe.
            if ($target === EventStatus::Live) {
                $updates['actual_start_at'] = now();
            }

            if ($target === EventStatus::Ended) {
                $updates['actual_end_at'] = now();
            }

            // Compare-and-swap on the source state: 0 rows affected means another
            // request transitioned the event first — we lost the race.
            $applied = Event::query()
                ->whereKey($event->getKey())
                ->where('status', $from->value)
                ->update($updates);

            if ($applied === 0) {
                throw new EventTransitionConflictException($from, $target);
            }

            $event->refresh();

            EventStatusChanged::dispatch($event, $from, $target);

            $this->audit->log('event.transitioned', actor: $actor, tenant: $event->tenant, auditable: $event, context: [
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $event;
        });
    }
}
