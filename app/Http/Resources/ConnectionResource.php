<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Networking\Models\Connection;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'message' => $this->message,
            'requester' => $this->person($this->requester),
            'addressee' => $this->person($this->addressee),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function person(?Attendee $attendee): ?array
    {
        return $attendee === null ? null : ['id' => $attendee->ulid, 'name' => $attendee->name];
    }
}
