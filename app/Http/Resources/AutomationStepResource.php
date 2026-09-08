<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Automation\Models\AutomationStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AutomationStep
 */
class AutomationStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'position' => $this->position,
            'type' => $this->type->value,
            'config' => $this->config,
            'conditions' => $this->conditions,
        ];
    }
}
