<?php

declare(strict_types=1);

namespace App\Application\Enterprise\DTOs;

use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Identity\Models\User;

/**
 * A completed SSO login: the local account, and the IdP identity it came from
 * (its `session` lets single logout end the IdP session later).
 */
final class SsoLoginResult
{
    public function __construct(
        public readonly User $user,
        public readonly ExternalIdentity $identity,
    ) {}
}
