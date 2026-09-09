<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The safe representation of an SSO connection. The encrypted `config`
 * (secrets/endpoints) is never serialized.
 *
 * @mixin SsoConnection
 */
class SsoConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'provider' => $this->provider->value,
            'display_name' => $this->display_name,
            'domain' => $this->domain,
            'default_role' => $this->default_role->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
