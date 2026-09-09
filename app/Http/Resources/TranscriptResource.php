<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Content\Models\Transcript;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transcript
 */
class TranscriptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'provider' => $this->provider,
            'language' => $this->language,
            'status' => $this->status->value,
            'segments' => TranscriptSegmentResource::collection($this->whenLoaded('segments')),
        ];
    }
}
