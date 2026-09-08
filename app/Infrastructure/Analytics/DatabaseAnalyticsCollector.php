<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use Carbon\CarbonInterface;

/**
 * MySQL-backed collector: appends a versioned row to `analytics_events`. The
 * tenant is taken from the event itself (not ambient context) so telemetry is
 * always attributed to the right tenant. Secret-looking keys are dropped
 * defensively — analytics payloads must never carry secrets.
 */
final class DatabaseAnalyticsCollector implements AnalyticsCollector
{
    /**
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password', 'token', 'secret', 'authorization', 'api_key', 'apikey',
        'access_token', 'refresh_token', 'stream_key', 'join_token',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AnalyticsEventName $name,
        Event $event,
        ?Attendee $attendee = null,
        array $properties = [],
        ?CarbonInterface $occurredAt = null,
    ): void {
        AnalyticsEvent::query()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->getKey(),
            'attendee_id' => $attendee?->getKey(),
            'name' => $name,
            'version' => $name->version(),
            'properties' => $this->sanitize($properties),
            'occurred_at' => $occurredAt ?? now(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitize(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $properties[$key] = '[redacted]';
            }
        }

        return $properties;
    }
}
