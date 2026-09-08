<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Automation\ResumeAutomationRunsAction;
use Illuminate\Console\Command;

/**
 * Continues automation runs whose wait delay has elapsed (ADR-025). Scheduled
 * frequently; withoutOverlapping.
 */
class ResumeAutomationsCommand extends Command
{
    protected $signature = 'automations:resume {--limit=100 : Max runs to resume per run}';

    protected $description = 'Resume automation runs parked by a wait step';

    public function handle(ResumeAutomationRunsAction $action): int
    {
        $count = $action->execute((int) $this->option('limit'));

        $this->info("Resumed {$count} automation run(s).");

        return self::SUCCESS;
    }
}
