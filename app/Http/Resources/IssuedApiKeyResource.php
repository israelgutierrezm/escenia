<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Enterprise\DTOs\IssuedApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one-time creation response for an API key: the raw token plus the key's
 * safe metadata. The token is shown here and never again.
 */
class IssuedApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IssuedApiKey $issued */
        $issued = $this->resource;

        return [
            'token' => $issued->plainToken,
            'key' => ApiKeyResource::make($issued->apiKey)->toArray($request),
        ];
    }
}
