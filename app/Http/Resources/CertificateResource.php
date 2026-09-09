<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Education\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Certificate
 */
class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'code' => $this->code,
            'recipient_name' => $this->recipient_name,
            'issued_at' => $this->issued_at->toIso8601String(),
            'verify_url' => url("/api/v1/certificates/verify/{$this->code}"),
        ];
    }
}
