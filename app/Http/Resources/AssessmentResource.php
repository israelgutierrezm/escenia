<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Education\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Host view of an assessment — includes the `correct` flags for editing.
 *
 * @mixin Assessment
 */
class AssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'passing_score' => $this->passing_score,
            'is_published' => $this->is_published,
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($q): array => [
                'id' => $q->ulid,
                'prompt' => $q->prompt,
                'type' => $q->type->value,
                'points' => $q->points,
                'options' => $q->options,
            ])->all()),
        ];
    }
}
