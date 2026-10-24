<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\Models\AutomationStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A webhook's URL and secret are credentials (many receivers authenticate by
 * the URL alone): the step shows only the destination's origin and how the
 * payload is signed, never the values (ADR-037, ADR-038).
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
        $now = now()->getTimestamp();

        return [
            'id' => $this->ulid,
            'position' => $this->position,
            'type' => $this->type->value,
            'config' => $isWebhook ? ['target' => $endpoint?->origin()] : $this->config,
            'has_secret' => $this->when($isWebhook, $endpoint?->secret !== null),
            // Still signs the pre-ADR-038 X-Escenia-Signature header.
            'legacy_signature' => $this->when($isWebhook, $endpoint?->legacySecret($now) !== null),
            // While a rotation's grace lasts, the previous secret also signs.
            'previous_secret_expires_at' => $this->when($isWebhook, $endpoint?->previousSecretExpiresAt($now)?->toIso8601String()),
            'conditions' => $this->conditions,
        ];
    }
}
