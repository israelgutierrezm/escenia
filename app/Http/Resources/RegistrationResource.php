<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Registration\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Host-side view of a registrant: who registered and what they answered.
 *
 * @mixin Registration
 */
class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'answers' => $this->answers,
            'registered_at' => $this->created_at?->toIso8601String(),
            'contact' => [
                'name' => $this->whenLoaded('contact', fn () => $this->contact->name),
                'email' => $this->whenLoaded('contact', fn () => $this->contact->email),
            ],
        ];
    }
}
