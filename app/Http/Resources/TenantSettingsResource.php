<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The tenant's enterprise settings surface (residency + dedicated flag).
 *
 * @mixin Tenant
 */
class TenantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'data_region' => $this->data_region->value,
            'is_dedicated' => $this->is_dedicated,
        ];
    }
}
