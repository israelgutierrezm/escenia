<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Sponsorship\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sponsor
 */
class SponsorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'tier' => $this->tier->value,
            'logo_url' => $this->logo_url,
            'website_url' => $this->website_url,
            'position' => $this->position,
        ];
    }
}
