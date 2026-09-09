<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsExporter;

/**
 * Deterministic, network-free exporter for dev and tests. It keeps the rows it
 * received in memory so tests can assert what would have been shipped. Bound as
 * a singleton so the captured rows survive across a request within a test.
 */
final class FakeAnalyticsExporter implements AnalyticsExporter
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $exported = [];

    public function export(array $rows): void
    {
        foreach ($rows as $row) {
            $this->exported[] = $row;
        }
    }
}
