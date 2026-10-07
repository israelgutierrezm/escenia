<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

/**
 * Where to send the browser to end the IdP session too (single logout), and
 * the ID of that logout request, which the IdP's answer must reference.
 */
final class LogoutRedirect
{
    public function __construct(
        public readonly string $url,
        public readonly string $requestId,
    ) {}
}
