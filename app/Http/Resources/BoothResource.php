<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Sponsorship\Models\Booth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booth
 */
class BoothResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'leads_count' => $this->leads_count,
            'sponsor' => $this->whenLoaded('sponsor', fn (): array => [
                'id' => $this->sponsor->ulid,
                'name' => $this->sponsor->name,
                'tier' => $this->sponsor->tier->value,
            ]),
        ];
    }
}
