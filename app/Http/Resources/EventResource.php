<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'actual_start_at' => $this->actual_start_at?->toIso8601String(),
            'actual_end_at' => $this->actual_end_at?->toIso8601String(),
            'allowed_transitions' => array_map(
                static fn (EventStatus $status): string => $status->value,
                $this->status->allowedTransitions(),
            ),
            'workspace' => WorkspaceResource::make($this->whenLoaded('workspace')),
            'capabilities' => EventCapabilityResource::collection($this->whenLoaded('capabilities')),
            'sessions' => EventSessionResource::collection($this->whenLoaded('sessions')),
            'speakers' => EventSpeakerResource::collection($this->whenLoaded('speakers')),
            'schedule' => EventScheduleItemResource::collection($this->whenLoaded('scheduleItems')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
