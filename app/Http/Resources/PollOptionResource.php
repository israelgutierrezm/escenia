<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Engagement\Models\PollOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PollOption
 */
class PollOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'label' => $this->label,
            'position' => $this->position,
            'votes_count' => $this->votes_count,
        ];
    }
}
