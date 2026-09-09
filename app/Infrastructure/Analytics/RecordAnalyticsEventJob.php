<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Persists an analytics row off the request thread (the `queue` capture mode,
 * ADR-031). It carries only primitives, re-resolves the event/attendee unscoped,
 * and delegates to the synchronous MySQL collector so the write logic stays in
 * one place. It depends on the concrete collector on purpose: the contract would
 * resolve back to the queued collector and re-enqueue forever. A missing event
 * (deleted meanwhile) is dropped silently — analytics is best-effort and must
 * never poison the queue.
 *
 * Lives in Infrastructure because it is the async arm of the analytics store
 * adapter, not a domain use-case.
 */
class RecordAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly int $eventId,
        public readonly ?int $attendeeId,
        public readonly string $name,
        public readonly array $properties,
        public readonly string $occurredAt,
    ) {}

    public function handle(DatabaseAnalyticsCollector $collector): void
    {
        $event = Event::query()->withoutGlobalScopes()->find($this->eventId);

        if ($event === null) {
            return;
        }

        $attendee = $this->attendeeId !== null
            ? Attendee::query()->withoutGlobalScopes()->find($this->attendeeId)
            : null;

        $collector->record(
            AnalyticsEventName::from($this->name),
            $event,
            $attendee,
            $this->properties,
            Carbon::parse($this->occurredAt),
        );
    }
}
