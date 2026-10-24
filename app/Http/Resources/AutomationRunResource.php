<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Models\AutomationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AutomationRun
 */
class AutomationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'trigger' => $this->trigger->value,
            'status' => $this->status->value,
            'current_position' => $this->current_position,
            // Failed attempts of the step at current_position (ADR-038).
            'step_attempts' => $this->step_attempts,
            // While running, resume_at is the worker's lease, not a schedule.
            'resume_at' => $this->status === AutomationRunStatus::Running ? null : $this->resume_at?->toIso8601String(),
            'log' => $this->log,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
