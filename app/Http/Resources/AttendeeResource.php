<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Registration\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The attendee's public-safe representation. The join token is NEVER included
 * here — it is returned exactly once, separately, at registration time.
 *
 * @mixin Attendee
 */
class AttendeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
