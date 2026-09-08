<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Engagement\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poll
 */
class PollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'question' => $this->question,
            'status' => $this->status->value,
            'options' => PollOptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
