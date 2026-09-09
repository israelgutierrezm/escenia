<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Content\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recording
 */
class RecordingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'duration_ms' => $this->duration_ms,
            'size_bytes' => $this->size_bytes,
            'format' => $this->format,
            'playback_url' => $this->playbackUrl(),
            'tracks' => RecordingTrackResource::collection($this->whenLoaded('tracks')),
            'transcripts' => TranscriptResource::collection($this->whenLoaded('transcripts')),
            'clips' => ClipResource::collection($this->whenLoaded('clips')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function playbackUrl(): ?string
    {
        if ($this->status !== RecordingStatus::Ready || $this->disk === null || $this->storage_key === null) {
            return null;
        }

        return app(RecordingStorage::class)->playbackUrl($this->disk, $this->storage_key);
    }
}
