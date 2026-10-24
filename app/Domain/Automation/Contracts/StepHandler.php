<?php

declare(strict_types=1);

namespace App\Domain\Automation\Contracts;

use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;

/**
 * Executes one type of automation step. Runs inside the run's tenant context.
 * A step can run more than once (a retry, or a worker that died mid-step), so
 * handlers should be idempotent where practical. For an external call, return
 * `retry` on a transient failure and `failed` on a permanent one instead of
 * throwing (ADR-038); a throw is retried a few times, then fails the run.
 */
interface StepHandler
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome;
}
