<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Commerce\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe view of a connected gateway. Credentials and the webhook secret
 * are NEVER serialized.
 *
 * @mixin PaymentAccount
 */
class PaymentAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'gateway' => $this->gateway->value,
            'display_name' => $this->display_name,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'webhook_url' => url("/api/v1/checkout/webhooks/{$this->ulid}"),
        ];
    }
}
