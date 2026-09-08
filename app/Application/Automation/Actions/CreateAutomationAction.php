<?php

declare(strict_types=1);

namespace App\Application\Automation\Actions;

use App\Application\Automation\DTOs\CreateAutomationData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Automation\Models\Automation;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates an automation with its ordered steps. Step positions follow the
 * submitted order.
 */
final class CreateAutomationAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, CreateAutomationData $data): Automation
    {
        return DB::transaction(function () use ($actor, $data): Automation {
            $automation = Automation::query()->create([
                'name' => $data->name,
                'trigger' => $data->trigger,
                'is_active' => true,
                'conditions' => $data->conditions,
                'created_by' => $actor->getKey(),
            ]);

            foreach ($data->steps as $position => $step) {
                AutomationStep::query()->create([
                    'automation_id' => $automation->getKey(),
                    'position' => $position,
                    'type' => $step['type'],
                    'config' => $step['config'],
                    'conditions' => $step['conditions'],
                ]);
            }

            $this->audit->log('automation.created', actor: $actor, tenant: $automation->tenant, auditable: $automation, context: [
                'trigger' => $data->trigger->value,
            ]);

            return $automation->load('steps');
        });
    }
}
