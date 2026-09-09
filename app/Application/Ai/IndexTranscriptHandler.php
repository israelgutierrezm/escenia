<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\EmbeddingProvider;
use App\Domain\Ai\Contracts\VectorIndex;
use App\Domain\Ai\Models\ContentChunk;
use App\Domain\Content\Enums\TranscriptStatus;
use App\Domain\Content\Models\Transcript;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Outbox consumer (ADR-027): when a transcript is ready, embeds its segments and
 * writes the searchable `content_chunks` (Semantic Replay / RAG). Idempotent —
 * re-indexing replaces the transcript's existing chunks. Runs inside the
 * transcript's tenant context.
 */
final class IndexTranscriptHandler implements OutboxHandler
{
    public function __construct(
        private readonly EmbeddingProvider $embeddings,
        private readonly VectorIndex $vectorIndex,
    ) {}

    public function handle(OutboxEvent $event): void
    {
        $transcriptId = (int) ($event->payload['transcript_id'] ?? 0);
        $transcript = Transcript::query()->find($transcriptId);

        if ($transcript === null || $transcript->status !== TranscriptStatus::Ready) {
            return;
        }

        $recording = $transcript->recording()->firstOrFail();

        $segments = $transcript->segments()->get();

        if ($segments->isEmpty()) {
            return;
        }

        $vectors = $this->embeddings->embed($segments->pluck('text')->map(static fn ($t): string => (string) $t)->all());

        DB::transaction(function () use ($transcript, $recording, $segments, $vectors): void {
            ContentChunk::query()->where('transcript_id', $transcript->getKey())->delete();

            foreach ($segments->values() as $position => $segment) {
                $chunk = ContentChunk::query()->create([
                    'event_id' => $recording->event_id,
                    'recording_id' => $recording->getKey(),
                    'transcript_id' => $transcript->getKey(),
                    'position' => $position,
                    'start_ms' => $segment->start_ms,
                    'end_ms' => $segment->end_ms,
                    'text' => $segment->text,
                    'vector' => $vectors[$position] ?? [],
                ]);

                $this->vectorIndex->index($chunk);
            }
        });
    }
}
