<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Networking\Models\Meeting;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'topic' => $this->topic,
            'proposer' => $this->person($this->proposer),
            'invitee' => $this->person($this->invitee),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function person(?Attendee $attendee): ?array
    {
        return $attendee === null ? null : ['id' => $attendee->ulid, 'name' => $attendee->name];
    }
}
