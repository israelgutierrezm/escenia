<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Outbox\DispatchOutboxAction;
use Illuminate\Console\Command;

/**
 * Publishes pending transactional-outbox events (ADR-007). Scheduled to run
 * frequently; safe to run concurrently is NOT guaranteed, so it is scheduled
 * withoutOverlapping.
 */
class DispatchOutboxCommand extends Command
{
    protected $signature = 'outbox:dispatch {--limit=100 : Max events to publish per run}';

    protected $description = 'Publish pending transactional-outbox events';

    public function handle(DispatchOutboxAction $action): int
    {
        $count = $action->execute((int) $this->option('limit'));

        $this->info("Published {$count} outbox event(s).");

        return self::SUCCESS;
    }
}
