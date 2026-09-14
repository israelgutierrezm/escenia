<?php

declare(strict_types=1);

namespace App\Application\Networking\DTOs;

use Illuminate\Support\Carbon;

/**
 * The details a proposer supplies when requesting a 1:1 meeting.
 */
final class ProposeMeetingData
{
    public function __construct(
        public readonly Carbon $scheduledAt,
        public readonly int $durationMinutes,
        public readonly ?string $topic,
    ) {}
}
