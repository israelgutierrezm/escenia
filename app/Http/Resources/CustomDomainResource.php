<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Enterprise\Models\CustomDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomDomain
 */
class CustomDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'hostname' => $this->hostname,
            'status' => $this->status->value,
            'target' => $this->target,
            'dns_challenge' => $this->dnsChallenge(),
            'workspace_id' => $this->whenLoaded('workspace', fn () => $this->workspace?->ulid),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
