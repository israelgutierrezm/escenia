<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Studio\Models\StudioParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudioParticipant
 */
class StudioParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'role' => $this->role->value,
            'stage' => $this->stage->value,
            'device_checked' => $this->device_checked,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'left_at' => $this->left_at?->toIso8601String(),
        ];
    }
}
