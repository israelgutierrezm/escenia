<?php

declare(strict_types=1);

namespace App\Application\Enterprise\DTOs;

/**
 * What the start of an SSO login hands back to HTTP: where to send the browser,
 * the `state` the SPA echoes on the callback, and the binding for the cookie.
 */
final class SsoLoginStart
{
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $state,
        public readonly string $binding,
    ) {}
}
