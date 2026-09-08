<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Commerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'total' => $this->total()->toArray(),
            'gateway' => $this->gateway,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
