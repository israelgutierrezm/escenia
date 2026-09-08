<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;

/**
 * Parks the run for a delay — the primitive behind sequences. `config.seconds`
 * is how long to wait before the next step runs.
 */
final class WaitStepHandler implements StepHandler
{
    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $seconds = max(1, (int) ($step->config['seconds'] ?? 60));

        return StepOutcome::waitUntil(now()->addSeconds($seconds), "wait {$seconds}s");
    }
}
