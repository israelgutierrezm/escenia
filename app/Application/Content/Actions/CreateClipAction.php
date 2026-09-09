<?php

declare(strict_types=1);

namespace App\Application\Content\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Content\Enums\ClipStatus;
use App\Domain\Content\Exceptions\InvalidClipRangeException;
use App\Domain\Content\Models\Clip;
use App\Domain\Content\Models\Recording;
use App\Domain\Identity\Models\User;

/**
 * Cuts a clip from a recording (content studio / editor). Stores the in/out
 * range; rendering the trimmed asset is future.
 */
final class CreateClipAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Recording $recording, User $actor, string $title, int $startMs, int $endMs): Clip
    {
        if ($endMs <= $startMs) {
            throw new InvalidClipRangeException;
        }

        $clip = Clip::query()->create([
            'recording_id' => $recording->getKey(),
            'title' => $title,
            'start_ms' => $startMs,
            'end_ms' => $endMs,
            'status' => ClipStatus::Draft,
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->log('content.clip.created', actor: $actor, tenant: $recording->tenant, auditable: $clip);

        return $clip;
    }
}
