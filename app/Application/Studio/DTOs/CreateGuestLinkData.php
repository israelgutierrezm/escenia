<?php

declare(strict_types=1);

namespace App\Application\Studio\DTOs;

use App\Domain\Studio\Enums\ParticipantRole;

final class CreateGuestLinkData
{
    public function __construct(
        public readonly string $name,
        public readonly ParticipantRole $role,
        public readonly ?string $expiresAt = null,
        public readonly bool $singleUse = false,
        public readonly ?int $maxUses = null,
    ) {}
}
