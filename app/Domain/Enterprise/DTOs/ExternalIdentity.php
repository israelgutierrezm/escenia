<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

use App\Domain\Identity\Models\User;

/**
 * A verified identity returned by an external IdP, normalized to the fields the
 * provisioning flow needs. `subject` is the IdP's stable, opaque user id; email
 * is the key we link to a local {@see User}.
 */
final class ExternalIdentity
{
    public function __construct(
        public readonly string $subject,
        public readonly string $email,
        public readonly string $name,
    ) {}
}
