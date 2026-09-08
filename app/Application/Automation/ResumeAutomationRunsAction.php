<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Tenancy\Context\TenantContext;

/**
 * Continues automation runs parked by a wait step whose `resume_at` is now due.
 * Reads unscoped across tenants (system process) and resumes each within its own
 * tenant context. Scheduled frequently, like the outbox dispatcher.
 */
final class ResumeAutomationRunsAction
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AutomationExecutor $executor,
    ) {}

    /**
     * @return int number of runs resumed
     */
    public function execute(int $limit = 100): int
    {
        $runs = AutomationRun::query()
            ->withoutGlobalScopes()
            ->where('status', AutomationRunStatus::Waiting->value)
            ->where('resume_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $resumed = 0;

        foreach ($runs as $run) {
            $tenant = $run->tenant;

            if ($tenant === null) {
                $this->executor->run($run);
            } else {
                $this->tenantContext->runFor($tenant, fn () => $this->executor->run($run));
            }

            $resumed++;
        }

        return $resumed;
    }
}
