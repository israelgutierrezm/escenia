<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

use Illuminate\Support\Carbon;

/**
 * A short-lived credential the client SDK uses to join the media room. Never
 * persisted; returned to the client and discarded server-side.
 */
final class AccessToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $url,
        public readonly string $identity,
        public readonly string $room,
        public readonly Carbon $expiresAt,
    ) {}
}
