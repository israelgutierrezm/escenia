<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Content\Models\RecordingTrack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordingTrack
 */
class RecordingTrackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'status' => $this->status->value,
            'size_bytes' => $this->size_bytes,
        ];
    }
}
