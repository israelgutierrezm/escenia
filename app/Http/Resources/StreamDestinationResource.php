<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Broadcasting\Models\StreamDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StreamDestination
 */
class StreamDestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The stream key is a secret and is intentionally never exposed.
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'protocol' => $this->protocol->value,
            'url' => $this->url,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
