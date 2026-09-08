<?php

declare(strict_types=1);

namespace App\Application\Events\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Exceptions\InvalidEventTransitionException;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a guarded lifecycle transition (ADR-017): rejects illegal moves,
 * stamps actual start/end times, emits a domain event and records an audit entry.
 */
final class TransitionEventAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, EventStatus $target): Event
    {
        $from = $event->status;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidEventTransitionException($from, $target);
        }

        return DB::transaction(function () use ($event, $actor, $from, $target): Event {
            $event->status = $target;

            if ($target === EventStatus::Live && $event->actual_start_at === null) {
                $event->actual_start_at = now();
            }

            if ($target === EventStatus::Ended && $event->actual_end_at === null) {
                $event->actual_end_at = now();
            }

            $event->save();

            EventStatusChanged::dispatch($event, $from, $target);

            $this->audit->log('event.transitioned', actor: $actor, tenant: $event->tenant, auditable: $event, context: [
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $event;
        });
    }
}
