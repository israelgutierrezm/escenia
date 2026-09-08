<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Production\Models\SceneVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SceneVersion
 */
class SceneVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'version' => $this->version,
            'schema_version' => $this->schema_version,
            'definition' => $this->definition,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
