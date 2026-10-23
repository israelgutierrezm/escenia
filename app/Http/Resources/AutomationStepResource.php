<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\Models\AutomationStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A webhook's URL and secret are credentials (many receivers authenticate by
 * the URL alone): the step shows only the destination's origin and whether the
 * payload is signed, never the values (ADR-037).
 *
 * @mixin AutomationStep
 */
class AutomationStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isWebhook = $this->type === AutomationStepType::Webhook;
        $endpoint = $this->webhookEndpoint();

        return [
            'id' => $this->ulid,
            'position' => $this->position,
            'type' => $this->type->value,
            'config' => $isWebhook ? ['target' => $endpoint?->origin()] : $this->config,
            'has_secret' => $this->when($isWebhook, $endpoint?->secret !== null),
            'conditions' => $this->conditions,
        ];
    }
}
