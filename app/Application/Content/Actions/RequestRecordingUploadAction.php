<?php

declare(strict_types=1);

namespace App\Application\Content\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Content\Contracts\RecordingStorage;
use App\Domain\Content\Enums\RecordingSource;
use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\ValueObjects\UploadTicket;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Starts a local-recording upload: creates a pending Recording and issues a
 * signed direct-to-storage ticket. The client uploads the binary straight to
 * object storage and then calls complete — Laravel never receives the file.
 *
 * @phpstan-type UploadResult array{recording: Recording, ticket: UploadTicket}
 */
final class RequestRecordingUploadAction
{
    private const CONTENT_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/webm' => 'weba',
    ];

    public function __construct(
        private readonly RecordingStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{recording: Recording, ticket: UploadTicket}
     */
    public function execute(Event $event, User $actor, string $contentType, ?string $title): array
    {
        $recording = Recording::query()->create([
            'event_id' => $event->getKey(),
            'source' => RecordingSource::Upload,
            'status' => RecordingStatus::Pending,
            'title' => $title,
            'created_by' => $actor->getKey(),
        ]);

        $extension = self::CONTENT_EXTENSIONS[$contentType] ?? 'bin';
        $key = "recordings/{$recording->ulid}/source.{$extension}";

        $ticket = $this->storage->uploadTicket($key, $contentType);

        $recording->forceFill(['disk' => $ticket->disk, 'storage_key' => $ticket->key])->save();

        $this->audit->log('content.recording.upload_requested', actor: $actor, tenant: $event->tenant, auditable: $recording);

        return ['recording' => $recording, 'ticket' => $ticket];
    }
}
