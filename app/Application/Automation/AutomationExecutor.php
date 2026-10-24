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
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs an automation's steps from its current position until a wait parks it,
 * a step needs another attempt, or the sequence completes. Per-step conditions
 * gate each step (conditional logic). Assumes the caller established the run's
 * tenant context and holds its lease (ADR-038).
 *
 * - Checkpoint after every step: a finished step never runs again, and a run
 *   whose worker died resumes from its last checkpoint.
 * - A step that reports `retry` is attempted again later (same position); when
 *   its attempts run out it gives up and the sequence goes on. A step that
 *   throws gets a few attempts too, then the run fails.
 * - Every write is fenced on the lease: a worker that lost it (another one took
 *   over after it expired) stops instead of overwriting the run.
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
        private readonly RetryPolicy $retries,
    ) {}

    public function run(AutomationRun $run): AutomationRun
    {
        if ($run->status !== AutomationRunStatus::Running || $run->resume_at === null) {
            return $run; // not leased by the caller
        }

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
            $attempt = $run->step_attempts + 1;

            if (! $this->conditions->passes($step->conditions, $context)) {
                $log[] = $this->entry($i, $step, 'skipped (conditions)', 'skipped', $attempt);

                if (! $this->advance($run, $i + 1, $log)) {
                    return $run;
                }

                continue;
            }

            try {
                $outcome = app(self::HANDLERS[$step->type->value])->handle($run, $step, $context);
            } catch (Throwable $e) {
                return $this->afterError($run, $step, $i, $attempt, $e, $log);
            }

            if ($outcome->isRetry()) {
                $delay = $this->retries->delayAfter($attempt, false, $outcome->retryAfterSeconds);

                if ($delay !== null) {
                    $at = now()->addSeconds($delay);
                    $log[] = $this->entry($i, $step, $outcome->summary, 'retry', $attempt, $outcome->details + [
                        'max_attempts' => $this->retries->maxAttempts(false),
                        'next_attempt_at' => $at->toIso8601String(),
                    ]);

                    $this->park($run, $i, $attempt, $at, $log);

                    return $run;
                }

                $log[] = $this->entry($i, $step, $outcome->summary.' (gave up)', 'gave_up', $attempt, $outcome->details);
            } else {
                $log[] = $this->entry($i, $step, $outcome->summary, $outcome->kind->value, $attempt, $outcome->details);
            }

            if ($outcome->isWait()) {
                $this->save($run, [
                    'status' => AutomationRunStatus::Waiting,
                    'current_position' => $i + 1,
                    'step_attempts' => 0,
                    'resume_at' => $outcome->waitUntil,
                    'log' => $log,
                ]);

                return $run;
            }

            if (! $this->advance($run, $i + 1, $log)) {
                return $run;
            }
        }

        $this->save($run, [
            'status' => AutomationRunStatus::Completed,
            'current_position' => count($steps),
            'step_attempts' => 0,
            'resume_at' => null,
            'log' => $log,
            'completed_at' => now(),
        ]);

        return $run;
    }

    /**
     * Checkpoint after a finished step, renewing the lease.
     *
     * @param  array<int, array<string, mixed>>  $log
     */
    private function advance(AutomationRun $run, int $next, array $log): bool
    {
        return $this->save($run, [
            'current_position' => $next,
            'step_attempts' => 0,
            'resume_at' => now()->addSeconds(AutomationRun::LEASE_SECONDS),
            'log' => $log,
        ]);
    }

    /**
     * Attempt the same step again at `$at`: the position does not move.
     *
     * @param  array<int, array<string, mixed>>  $log
     */
    private function park(AutomationRun $run, int $position, int $attempt, CarbonInterface $at, array $log): void
    {
        $this->save($run, [
            'status' => AutomationRunStatus::Waiting,
            'current_position' => $position,
            'step_attempts' => $attempt,
            'resume_at' => $at,
            'log' => $log,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $log
     */
    private function afterError(AutomationRun $run, AutomationStep $step, int $position, int $attempt, Throwable $e, array $log): AutomationRun
    {
        // The run log is visible to automations.view: the exception class only.
        $summary = 'error: '.class_basename($e);
        $delay = $this->retries->delayAfter($attempt, true);

        if ($delay !== null) {
            $at = now()->addSeconds($delay);
            Log::warning('automation.step_error', ['run' => $run->ulid, 'position' => $position, 'attempt' => $attempt, 'error' => $e::class]);
            $log[] = $this->entry($position, $step, $summary, 'retry', $attempt, [
                'max_attempts' => $this->retries->maxAttempts(true),
                'next_attempt_at' => $at->toIso8601String(),
            ]);

            $this->park($run, $position, $attempt, $at, $log);

            return $run;
        }

        report($e);
        $log[] = $this->entry($position, $step, $summary, 'failed', $attempt);

        $this->save($run, [
            'status' => AutomationRunStatus::Failed,
            'failed_reason' => Str::limit($e::class.': '.$e->getMessage(), 250, ''),
            'current_position' => $position,
            'step_attempts' => $attempt,
            'resume_at' => null,
            'log' => $log,
        ]);

        return $run;
    }

    /**
     * Writes only while this worker still holds the lease it was given (the run
     * is `running` with the same `resume_at`); every save also changes the log
     * or the status, so a matching row always reports as updated.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function save(AutomationRun $run, array $attributes): bool
    {
        $columns = ['updated_at' => now()];

        foreach ($attributes as $key => $value) {
            $columns[$key] = match (true) {
                $value instanceof AutomationRunStatus => $value->value,
                is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                default => $value,
            };
        }

        $saved = AutomationRun::query()
            ->withoutGlobalScopes()
            ->whereKey($run->getKey())
            ->where('status', AutomationRunStatus::Running->value)
            ->where('resume_at', $run->resume_at)
            ->update($columns);

        if ($saved !== 1) {
            Log::warning('automation.lease_lost', ['run' => $run->ulid]);

            return false;
        }

        $run->forceFill($attributes)->syncOriginal();

        return true;
    }

    /**
     * @param  array<string, scalar|null>  $details
     * @return array<string, mixed>
     */
    private function entry(int $position, AutomationStep $step, string $summary, string $outcome, int $attempt, array $details = []): array
    {
        return [
            'position' => $position,
            'type' => $step->type->value,
            'summary' => $summary,
            'outcome' => $outcome,
            'attempt' => $attempt,
            'at' => now()->toIso8601String(),
        ] + $details;
    }
}
