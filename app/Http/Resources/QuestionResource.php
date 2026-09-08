<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Engagement\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'status' => $this->status->value,
            'answer' => $this->answer,
            'votes_count' => $this->votes_count,
            'answered_at' => $this->answered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
