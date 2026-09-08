<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Broadcasting\Models\BroadcastDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BroadcastDestination
 */
class BroadcastDestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'destination' => $this->whenLoaded('destination', fn () => $this->destination?->ulid),
        ];
    }
}
