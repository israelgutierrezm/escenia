<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Sponsorship\Models\BoothLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Host view of a captured lead.
 *
 * @mixin BoothLead
 */
class BoothLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'booth' => $this->whenLoaded('booth', fn () => $this->booth->ulid),
            'attendee' => [
                'name' => $this->whenLoaded('attendee', fn () => $this->attendee->name),
                'email' => $this->whenLoaded('attendee', fn () => $this->attendee->email),
            ],
        ];
    }
}
