<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\ValueObjects\UploadTicket;

/**
 * Deterministic, network-free storage for dev and tests. It issues plausible
 * upload/playback URLs without touching any bucket. No real bytes move.
 */
final class FakeRecordingStorage implements RecordingStorage
{
    private const DISK = 'fake';

    private const BASE = 'https://fake-storage.local';

    public function uploadTicket(string $key, string $contentType): UploadTicket
    {
        return new UploadTicket(
            url: self::BASE.'/upload/'.ltrim($key, '/'),
            method: 'PUT',
            headers: ['Content-Type' => $contentType],
            disk: self::DISK,
            key: $key,
            expiresAt: now()->addHour(),
        );
    }

    public function playbackUrl(string $disk, string $key): string
    {
        return self::BASE.'/play/'.ltrim($key, '/');
    }
}
