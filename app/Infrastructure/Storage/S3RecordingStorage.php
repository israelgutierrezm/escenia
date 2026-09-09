<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\ValueObjects\UploadTicket;
use Illuminate\Support\Facades\Storage;

/**
 * S3/R2-backed storage: issues presigned PUT upload URLs and temporary playback
 * URLs against the configured disk. Not integration-tested against a bucket (see
 * technical-debt); the fake storage is the default in dev/tests.
 */
final class S3RecordingStorage implements RecordingStorage
{
    public function __construct(
        private readonly string $disk,
    ) {}

    public function uploadTicket(string $key, string $contentType): UploadTicket
    {
        $expiresAt = now()->addHour();

        /** @var array{url: string, headers: array<string, mixed>} $signed */
        $signed = Storage::disk($this->disk)->temporaryUploadUrl($key, $expiresAt, ['ContentType' => $contentType]);

        $headers = [];
        foreach ($signed['headers'] as $name => $value) {
            $headers[(string) $name] = is_scalar($value) ? (string) $value : '';
        }

        return new UploadTicket(
            url: $signed['url'],
            method: 'PUT',
            headers: $headers,
            disk: $this->disk,
            key: $key,
            expiresAt: $expiresAt,
        );
    }

    public function playbackUrl(string $disk, string $key): string
    {
        return Storage::disk($disk)->temporaryUrl($key, now()->addHour());
    }
}
