<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Tenancy\Context\TenantContext;

/**
 * Continues automation runs that are due: parked by a wait step or waiting for
 * the next attempt of a failed step, plus runs whose worker died (`running`
 * with an expired lease, resumed from their last checkpoint). Each run is
 * claimed with a compare-and-set before it runs, so two workers never execute
 * the same run (ADR-038). Reads unscoped across tenants (system process) and
 * resumes each within its own tenant context, within a time budget per tick.
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
    public function execute(int $limit = 100, int $budgetSeconds = 50): int
    {
        $deadline = now()->addSeconds($budgetSeconds);

        $runs = AutomationRun::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [AutomationRunStatus::Waiting->value, AutomationRunStatus::Running->value])
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', now())
            ->orderBy('resume_at')
            ->limit($limit)
            ->get();

        $resumed = 0;

        foreach ($runs as $run) {
            if (now()->greaterThanOrEqualTo($deadline)) {
                break;
            }

            if (! $this->claim($run)) {
                continue;
            }

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

    /**
     * Takes the run with a fresh lease, only if nobody changed it since it was
     * read (same status and `resume_at`).
     */
    public function claim(AutomationRun $run): bool
    {
        $lease = now()->addSeconds(AutomationRun::LEASE_SECONDS);

        $claimed = AutomationRun::query()
            ->withoutGlobalScopes()
            ->whereKey($run->getKey())
            ->where('status', $run->status->value)
            ->where('resume_at', $run->resume_at)
            ->update([
                'status' => AutomationRunStatus::Running->value,
                'resume_at' => $lease,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        $run->forceFill(['status' => AutomationRunStatus::Running, 'resume_at' => $lease])->syncOriginal();

        return true;
    }
}
