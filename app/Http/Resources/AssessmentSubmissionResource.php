<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Education\Models\AssessmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssessmentSubmission
 */
class AssessmentSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'score' => $this->score,
            'passed' => $this->passed,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'attendee' => [
                'name' => $this->whenLoaded('attendee', fn () => $this->attendee->name),
                'email' => $this->whenLoaded('attendee', fn () => $this->attendee->email),
            ],
        ];
    }
}
