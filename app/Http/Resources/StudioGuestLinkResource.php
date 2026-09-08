<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Studio\Models\StudioGuestLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudioGuestLink
 */
class StudioGuestLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'role' => $this->role->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'single_use' => $this->single_use,
            'max_uses' => $this->max_uses,
            'uses' => $this->uses,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
