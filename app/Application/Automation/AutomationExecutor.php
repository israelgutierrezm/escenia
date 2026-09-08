<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Steps\NotifyStepHandler;
use App\Application\Automation\Steps\TagContactStepHandler;
use App\Application\Automation\Steps\WaitStepHandler;
use App\Application\Automation\Steps\WebhookStepHandler;
use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\Support\ConditionEvaluator;
use Throwable;

/**
 * Runs an automation's steps from its current position until a wait step parks
 * it or the sequence completes. Per-step conditions gate each step (conditional
 * logic). A throwing step fails the run (terminal); flaky external calls should
 * not throw (see StepHandler). Assumes the caller established the run's tenant
 * context.
 */
final class AutomationExecutor
{
    /**
     * @var array<string, class-string<StepHandler>>
     */
    private const HANDLERS = [
        'webhook' => WebhookStepHandler::class,
        'tag_contact' => TagContactStepHandler::class,
        'notify' => NotifyStepHandler::class,
        'wait' => WaitStepHandler::class,
    ];

    public function __construct(
        private readonly ConditionEvaluator $conditions,
    ) {}

    public function run(AutomationRun $run): AutomationRun
    {
        /** @var array<int, AutomationStep> $steps */
        $steps = AutomationStep::query()
            ->where('automation_id', $run->automation_id)
            ->orderBy('position')
            ->get()
            ->values()
            ->all();

        /** @var array<string, mixed> $context */
        $context = $run->context ?? [];
        /** @var array<int, array<string, mixed>> $log */
        $log = $run->log ?? [];

        for ($i = $run->current_position; $i < count($steps); $i++) {
            $step = $steps[$i];

            if (! $this->conditions->passes($step->conditions, $context)) {
                $log[] = ['position' => $i, 'type' => $step->type->value, 'summary' => 'skipped (conditions)'];

                continue;
            }

            try {
                $outcome = app(self::HANDLERS[$step->type->value])->handle($run, $step, $context);
            } catch (Throwable $e) {
                $run->forceFill([
                    'status' => AutomationRunStatus::Failed,
                    'failed_reason' => $e->getMessage(),
                    'current_position' => $i,
                    'log' => $log,
                ])->save();
                report($e);

                return $run;
            }

            $log[] = ['position' => $i, 'type' => $step->type->value, 'summary' => $outcome->summary];

            if ($outcome->isWait()) {
                $run->forceFill([
                    'status' => AutomationRunStatus::Waiting,
                    'current_position' => $i + 1,
                    'resume_at' => $outcome->waitUntil,
                    'log' => $log,
                ])->save();

                return $run;
            }
        }

        $run->forceFill([
            'status' => AutomationRunStatus::Completed,
            'current_position' => count($steps),
            'resume_at' => null,
            'log' => $log,
            'completed_at' => now(),
        ])->save();

        return $run;
    }
}
