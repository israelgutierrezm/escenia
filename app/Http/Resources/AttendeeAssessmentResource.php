<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Education\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attendee view of an assessment. Option `correct` flags are NEVER included — the
 * answer key stays server-side (ADR-028).
 *
 * @mixin Assessment
 */
class AttendeeAssessmentResource extends JsonResource
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
            'questions' => $this->questions->map(fn ($q): array => [
                'id' => $q->ulid,
                'prompt' => $q->prompt,
                'type' => $q->type->value,
                'options' => array_map(static fn (array $o): array => [
                    'key' => $o['key'] ?? null,
                    'label' => $o['label'] ?? null,
                ], $q->options),
            ])->all(),
        ];
    }
}
