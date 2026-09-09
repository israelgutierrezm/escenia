<?php

declare(strict_types=1);

namespace App\Application\Enterprise\DTOs;

use App\Domain\Enterprise\Models\ApiKey;

/**
 * Carries the freshly minted raw key alongside its persisted record. The raw
 * value exists only in this object for the duration of the create request and
 * is surfaced to the caller exactly once — it is never stored or logged.
 */
final class IssuedApiKey
{
    public function __construct(
        public readonly ApiKey $apiKey,
        public readonly string $plainToken,
    ) {}
}
