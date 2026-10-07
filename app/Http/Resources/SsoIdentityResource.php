<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Enterprise\Models\SsoIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A federated identity as the tenant admin sees it: which account an IdP
 * subject logs into, and when it last did.
 *
 * @mixin SsoIdentity
 */
class SsoIdentityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'subject' => $this->subject,
            'user' => [
                'id' => $this->user->ulid,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
