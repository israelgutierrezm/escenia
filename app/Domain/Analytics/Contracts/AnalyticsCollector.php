<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use Carbon\CarbonInterface;

/**
 * Records analytics telemetry to the analytics plane (ADR-005). The default
 * implementation writes to MySQL; this contract is the seam that lets the store
 * move to ClickHouse (or an async pipeline) without changing callers.
 *
 * Implementations MUST NOT persist secrets in the properties payload.
 */
interface AnalyticsCollector
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AnalyticsEventName $name,
        Event $event,
        ?Attendee $attendee = null,
        array $properties = [],
        ?CarbonInterface $occurredAt = null,
    ): void;
}
