<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Production\Models\BrandKit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BrandKit
 */
class BrandKitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'tokens' => $this->tokens,
            'is_default' => $this->is_default,
        ];
    }
}
