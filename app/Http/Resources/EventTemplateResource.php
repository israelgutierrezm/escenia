<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventTemplate
 */
class EventTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'type' => $this->type->value,
            'description' => $this->description,
            'default_capabilities' => $this->default_capabilities ?? [],
            'is_system' => $this->is_system,
        ];
    }
}
