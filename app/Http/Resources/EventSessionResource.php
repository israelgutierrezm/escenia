<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSession
 */
class EventSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'status' => $this->status->value,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'position' => $this->position,
        ];
    }
}
