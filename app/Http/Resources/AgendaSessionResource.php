<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A session as it appears in a multi-session agenda.
 *
 * @mixin EventSession
 */
class AgendaSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'registered_count' => $this->registered_count,
            'has_capacity' => $this->hasCapacityLeft(),
            'track' => $this->whenLoaded('track', fn () => $this->track?->ulid),
            'starts_at' => $this->scheduled_start_at?->toIso8601String(),
            'ends_at' => $this->scheduled_end_at?->toIso8601String(),
        ];
    }
}
