<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Analytics\ExtractAnalyticsAction;
use Illuminate\Console\Command;

/**
 * Ships unexported analytics rows to the warehouse sink (ADR-031). Scheduled
 * withoutOverlapping; idempotent thanks to the `exported_at` high-water marker.
 */
class ExtractAnalyticsCommand extends Command
{
    protected $signature = 'analytics:extract {--limit= : Max rows to export per run}';

    protected $description = 'Export unexported analytics rows to the warehouse sink';

    public function handle(ExtractAnalyticsAction $action): int
    {
        $limit = $this->option('limit');
        $count = $action->execute($limit !== null ? (int) $limit : (int) config('analytics.export_batch', 500));

        $this->info("Exported {$count} analytics row(s).");

        return self::SUCCESS;
    }
}
