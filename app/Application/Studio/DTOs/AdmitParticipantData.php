<?php

declare(strict_types=1);

namespace App\Application\Studio\DTOs;

use App\Domain\Studio\Enums\ParticipantRole;

final class AdmitParticipantData
{
    public function __construct(
        public readonly string $name,
        public readonly ParticipantRole $role,
        public readonly ?int $userId = null,
    ) {}
}
