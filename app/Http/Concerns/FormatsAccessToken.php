<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Media\ValueObjects\AccessToken;

trait FormatsAccessToken
{
    /**
     * @return array<string, mixed>
     */
    protected function accessTokenArray(AccessToken $token): array
    {
        return [
            'token' => $token->token,
            'url' => $token->url,
            'identity' => $token->identity,
            'room' => $token->room,
            'expires_at' => $token->expiresAt->toIso8601String(),
        ];
    }
}
