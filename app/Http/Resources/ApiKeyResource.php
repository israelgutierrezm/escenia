<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Enterprise\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The safe representation of an API key: the secret is never included (only its
 * prefix). The raw key is returned exactly once at creation via
 * {@see IssuedApiKeyResource}.
 *
 * @mixin ApiKey
 */
class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'scopes' => $this->scopes,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
