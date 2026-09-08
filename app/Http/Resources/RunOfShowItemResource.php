<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Production\Models\RunOfShowItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RunOfShowItem
 */
class RunOfShowItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'notes' => $this->notes,
            'duration_seconds' => $this->duration_seconds,
            'position' => $this->position,
            'scene' => $this->whenLoaded('scene', fn () => $this->scene?->ulid),
        ];
    }
}
