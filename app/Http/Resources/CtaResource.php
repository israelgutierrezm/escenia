<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Commerce\Models\Cta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cta
 */
class CtaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket?->ulid),
            'is_active' => $this->is_active,
            'live' => $this->isLive(),
            'clicks_count' => $this->clicks_count,
            'position' => $this->position,
        ];
    }
}
