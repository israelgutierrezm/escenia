<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Enums\TriggerEvent;
use App\Domain\Automation\Models\Automation;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Support\ConditionEvaluator;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;

/**
 * Outbox consumer (ADR-025): turns a committed trigger event into automation
 * runs. Builds the context, finds active automations for the trigger, evaluates
 * their top-level conditions, and starts a run per match. A run is unique per
 * (automation, outbox_event) so an at-least-once trigger never double-fires a
 * sequence. Runs within the event's tenant context (set by the dispatcher).
 */
final class HandleAutomationTrigger implements OutboxHandler
{
    public function __construct(
        private readonly TriggerContextBuilder $contextBuilder,
        private readonly ConditionEvaluator $conditions,
        private readonly AutomationExecutor $executor,
    ) {}

    public function handle(OutboxEvent $event): void
    {
        $trigger = TriggerEvent::tryFrom($event->topic);

        if ($trigger === null) {
            return;
        }

        $context = $this->contextBuilder->build($trigger, $event->payload ?? []);

        $automations = Automation::query()
            ->where('trigger', $trigger->value)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->conditions->passes($automation->conditions, $context)) {
                continue;
            }

            $run = AutomationRun::query()->firstOrCreate(
                ['automation_id' => $automation->getKey(), 'outbox_event_id' => $event->getKey()],
                [
                    'trigger' => $trigger,
                    'context' => $context,
                    'status' => AutomationRunStatus::Running,
                    'current_position' => 0,
                ],
            );

            if ($run->wasRecentlyCreated) {
                $this->executor->run($run);
            }
        }
    }
}
