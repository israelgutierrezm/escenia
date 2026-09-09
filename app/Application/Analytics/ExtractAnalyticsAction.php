<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsExporter;
use App\Domain\Analytics\Models\AnalyticsEvent;

/**
 * Ships unexported analytics rows to the warehouse sink and marks them exported
 * (ADR-031). Reads unscoped across tenants (system process) with a high-water
 * marker (`exported_at`), so it is idempotent and resumable — a crashed run
 * simply re-selects the rows it had not yet marked. Resolves TD-017/TD-018:
 * aggregation/retention can move off MySQL once rows are safely in the warehouse.
 */
final class ExtractAnalyticsAction
{
    public function __construct(
        private readonly AnalyticsExporter $exporter,
    ) {}

    /**
     * @return int number of rows exported
     */
    public function execute(int $limit = 500): int
    {
        $rows = AnalyticsEvent::query()
            ->withoutGlobalScopes()
            ->whereNull('exported_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $this->exporter->export($rows->map(fn (AnalyticsEvent $row): array => $this->serialize($row))->all());

        AnalyticsEvent::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $rows->modelKeys())
            ->update(['exported_at' => now()]);

        return $rows->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AnalyticsEvent $row): array
    {
        return [
            'ulid' => $row->ulid,
            'tenant_id' => $row->tenant_id,
            'event_id' => $row->event_id,
            'attendee_id' => $row->attendee_id,
            'name' => $row->name->value,
            'version' => $row->version,
            'properties' => $row->properties,
            'occurred_at' => $row->occurred_at->toIso8601String(),
        ];
    }
}
