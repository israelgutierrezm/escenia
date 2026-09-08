<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventSpeaker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSpeaker
 */
class EventSpeakerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role->value,
            'position' => $this->position,
        ];
    }
}
