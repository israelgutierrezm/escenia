<?php

declare(strict_types=1);

namespace App\Domain\Automation\Contracts;

use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;

/**
 * Executes one type of automation step. Runs inside the run's tenant context.
 * A handler should be side-effect-idempotent where practical (steps can be
 * re-attempted after a wait), and must not throw for merely-flaky external
 * calls — return `done` with a summary instead so the sequence keeps moving.
 */
interface StepHandler
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome;
}
