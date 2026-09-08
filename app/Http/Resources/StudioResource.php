<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Studio\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Studio
 */
class StudioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $this->currentSession();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'current_session' => $current !== null ? StudioSessionResource::make($current) : null,
            'preview_scene' => $this->whenLoaded('previewScene', fn () => $this->previewScene?->ulid),
            'program_scene' => $this->whenLoaded('programScene', fn () => $this->programScene?->ulid),
        ];
    }
}
