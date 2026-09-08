<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Automation\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Automation
 */
class AutomationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'trigger' => $this->trigger->value,
            'is_active' => $this->is_active,
            'conditions' => $this->conditions,
            'steps' => AutomationStepResource::collection($this->whenLoaded('steps')),
        ];
    }
}
