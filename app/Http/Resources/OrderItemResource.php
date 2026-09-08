<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Commerce\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'ticket_name' => $this->ticket_name,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal()->toArray(),
        ];
    }
}
