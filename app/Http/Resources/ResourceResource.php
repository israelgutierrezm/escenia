<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Engagement\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a downloadable event resource (handout / slides / link).
 *
 * @mixin Resource
 */
class ResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'url' => $this->url,
            'position' => $this->position,
            'downloads_count' => $this->downloads_count,
        ];
    }
}
