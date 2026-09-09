<?php

declare(strict_types=1);

namespace App\Application\Ai\Actions;

use App\Application\Ai\Jobs\GenerateSummaryJob;
use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Ai\Enums\SummaryKind;
use App\Domain\Ai\Enums\SummaryStatus;
use App\Domain\Ai\Models\ContentSummary;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Content\Models\Recording;
use App\Domain\Identity\Models\User;

/**
 * Content Factory: requests an AI artifact (summary/chapters/highlights) for a
 * recording. Creates it pending and queues generation (heavy work off the
 * request thread).
 */
final class RequestSummaryAction
{
    public function __construct(
        private readonly AiCompletionProvider $ai,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Recording $recording, User $actor, SummaryKind $kind): ContentSummary
    {
        $summary = ContentSummary::query()->create([
            'recording_id' => $recording->getKey(),
            'kind' => $kind,
            'status' => SummaryStatus::Pending,
            'provider' => $this->ai->name(),
            'created_by' => $actor->getKey(),
        ]);

        GenerateSummaryJob::dispatch($summary->getKey());

        $this->audit->log('ai.summary.requested', actor: $actor, tenant: $recording->tenant, auditable: $summary, context: [
            'kind' => $kind->value,
        ]);

        return $summary;
    }
}
