<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

use App\Domain\Identity\Models\User;

/**
 * A verified identity returned by an external IdP, normalized to the fields the
 * provisioning flow needs. `subject` is the IdP's stable, opaque user id; email
 * is the key we link to a local {@see User}. `session` is whatever the protocol
 * needs to end the IdP session later (SAML NameID + SessionIndex), opaque to
 * the domain.
 */
final class ExternalIdentity
{
    /**
     * @param  array<string, string>  $session
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $email,
        public readonly string $name,
        public readonly array $session = [],
    ) {}
}
