<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventScheduleItem
 */
class EventScheduleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'position' => $this->position,
        ];
    }
}
