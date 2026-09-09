<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

/**
 * Ships a batch of analytics rows to the warehouse sink (ADR-031). Rows are
 * plain serialized maps, decoupled from Eloquent, so a ClickHouse/warehouse
 * adapter just forwards JSON. This is the seam that lets the analytics store
 * move off MySQL without touching the extraction logic (ADR-005).
 */
interface AnalyticsExporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function export(array $rows): void;
}
