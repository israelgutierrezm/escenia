<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use Carbon\CarbonInterface;

/**
 * Decouples the request from the analytics store by pushing the write to a
 * queue (ADR-031, resolves TD-016). Same {@see AnalyticsCollector} contract as
 * the synchronous collector, so callers never change — only config selects it.
 * The actual persistence happens in {@see RecordAnalyticsEventJob}, which
 * delegates to the MySQL collector.
 */
final class QueuedAnalyticsCollector implements AnalyticsCollector
{
    public function record(
        AnalyticsEventName $name,
        Event $event,
        ?Attendee $attendee = null,
        array $properties = [],
        ?CarbonInterface $occurredAt = null,
    ): void {
        RecordAnalyticsEventJob::dispatch(
            $event->getKey(),
            $attendee?->getKey(),
            $name->value,
            $properties,
            ($occurredAt ?? now())->toIso8601String(),
        );
    }
}
