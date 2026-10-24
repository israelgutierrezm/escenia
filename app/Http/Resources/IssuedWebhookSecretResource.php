<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Automation\DTOs\IssuedWebhookSecret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A just-issued webhook signing secret (ADR-038): the only response that ever
 * carries it. Served with `Cache-Control: no-store`.
 *
 * @property IssuedWebhookSecret $resource
 */
class IssuedWebhookSecretResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->resource->plainSecret,
            'step' => AutomationStepResource::make($this->resource->step)->resolve($request),
        ];
    }
}
