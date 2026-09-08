<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Events\Models\EventCapability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventCapability
 */
class EventCapabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'capability' => $this->capability->value,
            'enabled' => $this->enabled,
            'settings' => $this->settings,
        ];
    }
}
