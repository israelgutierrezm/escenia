<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            // Present only when the tenant was loaded through a membership pivot.
            'role' => $this->whenPivotLoaded('tenant_memberships', fn () => $this->pivot->getAttribute('role')),
            'membership_status' => $this->whenPivotLoaded('tenant_memberships', fn () => $this->pivot->getAttribute('status')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
