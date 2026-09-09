<?php

declare(strict_types=1);

namespace App\Application\Content\Actions;

use App\Application\Content\Jobs\TranscribeRecordingJob;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Content\Contracts\Transcriber;
use App\Domain\Content\Enums\TranscriptStatus;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\Models\Transcript;
use App\Domain\Identity\Models\User;

/**
 * Requests a transcript for a recording: creates it pending and queues the
 * transcription job (heavy work off the request thread).
 */
final class RequestTranscriptionAction
{
    public function __construct(
        private readonly Transcriber $transcriber,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Recording $recording, User $actor, string $language): Transcript
    {
        $transcript = Transcript::query()->create([
            'recording_id' => $recording->getKey(),
            'provider' => $this->transcriber->name(),
            'language' => $language,
            'status' => TranscriptStatus::Pending,
        ]);

        TranscribeRecordingJob::dispatch($transcript->getKey());

        $this->audit->log('content.transcript.requested', actor: $actor, tenant: $recording->tenant, auditable: $transcript);

        return $transcript;
    }
}
