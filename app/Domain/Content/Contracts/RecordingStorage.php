<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\ValueObjects\UploadTicket;

/**
 * Issues signed, direct-to-object-storage upload tickets and playback URLs so
 * large media never flows through the control plane (ADR-026). The default
 * implementation is network-free for dev/tests; S3/R2 is production.
 */
interface RecordingStorage
{
    public function uploadTicket(string $key, string $contentType): UploadTicket;

    public function playbackUrl(string $disk, string $key): string;
}
