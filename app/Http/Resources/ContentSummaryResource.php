<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ai\Models\ContentSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentSummary
 */
class ContentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
