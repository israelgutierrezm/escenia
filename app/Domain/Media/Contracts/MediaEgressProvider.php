<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\ValueObjects\EgressHandle;
use App\Domain\Media\ValueObjects\EgressSpec;
use App\Domain\Media\ValueObjects\RoomHandle;

/**
 * Egress capability of the media plane (composite egress to RTMP/SRT and/or
 * recording). Segregated from the room/token contract so providers implement
 * only what they support (ADR-002 / ADR-008). Laravel never transports media —
 * it only starts/stops egress and reads health.
 */
interface MediaEgressProvider
{
    public function startEgress(RoomHandle $room, EgressSpec $spec): EgressHandle;

    public function stopEgress(EgressHandle $egress): void;

    /**
     * @return string one of: healthy, degraded, failed, unknown
     */
    public function egressHealth(EgressHandle $egress): string;
}
