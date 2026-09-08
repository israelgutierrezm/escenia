<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Commerce\Models\Ticket;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price()->toArray(),
            'compare_at' => $this->compare_at_minor !== null
                ? Money::of($this->compare_at_minor, $this->currency)->toArray()
                : null,
            'capacity' => $this->capacity,
            'remaining' => $this->remaining(),
            'on_sale' => $this->isOnSale(),
            'position' => $this->position,
        ];
    }
}
