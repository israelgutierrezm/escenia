<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'resume_at' => $this->resume_at?->toIso8601String(),
            'log' => $this->log,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
