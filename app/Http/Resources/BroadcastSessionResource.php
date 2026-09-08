<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Broadcasting\Models\BroadcastSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BroadcastSession
 */
class BroadcastSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'health' => $this->health->value,
            'record' => $this->record,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'destinations' => BroadcastDestinationResource::collection($this->whenLoaded('destinations')),
        ];
    }
}
