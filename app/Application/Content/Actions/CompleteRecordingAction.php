<?php

declare(strict_types=1);

namespace App\Application\Content\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Content\Enums\TrackKind;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\Models\RecordingTrack;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Marks an uploaded recording ready once the client has finished the direct
 * upload, recording the reported metadata and creating the composite track that
 * points at the uploaded asset. The reported size/duration are the client's
 * assertion (hardening is future — see technical-debt).
 */
final class CompleteRecordingAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Recording $recording, User $actor, ?int $durationMs, ?int $sizeBytes, ?string $format): Recording
    {
        return DB::transaction(function () use ($recording, $actor, $durationMs, $sizeBytes, $format): Recording {
            $recording->forceFill([
                'status' => RecordingStatus::Ready,
                'duration_ms' => $durationMs,
                'size_bytes' => $sizeBytes,
                'format' => $format,
            ])->save();

            // The uploaded file is the composite program track.
            RecordingTrack::query()->firstOrCreate(
                ['recording_id' => $recording->getKey(), 'kind' => TrackKind::Composite->value],
                [
                    'label' => 'Composite',
                    'status' => RecordingStatus::Ready,
                    'disk' => $recording->disk,
                    'storage_key' => $recording->storage_key,
                    'size_bytes' => $sizeBytes,
                ],
            );

            $this->audit->log('content.recording.completed', actor: $actor, tenant: $recording->tenant, auditable: $recording);

            return $recording->load('tracks');
        });
    }
}
