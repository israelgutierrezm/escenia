<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Production\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Scene
 */
class SceneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'position' => $this->position,
            'current_version' => $this->currentVersion !== null
                ? SceneVersionResource::make($this->currentVersion)
                : null,
        ];
    }
}
