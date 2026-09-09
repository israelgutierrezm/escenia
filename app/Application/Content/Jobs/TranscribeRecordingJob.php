<?php

declare(strict_types=1);

namespace App\Application\Content\Jobs;

use App\Domain\Content\Contracts\Transcriber;
use App\Domain\Content\Enums\TranscriptStatus;
use App\Domain\Content\Models\Transcript;
use App\Domain\Content\Models\TranscriptSegment;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transcribes a recording off the request thread (CLAUDE.md: heavy work in
 * jobs). Loads the transcript unscoped, runs inside its tenant context, calls
 * the Transcriber, and persists the segments — marking the transcript ready or
 * failed.
 */
class TranscribeRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $transcriptId,
    ) {}

    public function handle(Transcriber $transcriber, TenantContext $tenantContext): void
    {
        $transcript = Transcript::query()->withoutGlobalScopes()->find($this->transcriptId);

        if ($transcript === null || $transcript->status === TranscriptStatus::Ready) {
            return;
        }

        $tenantContext->runFor($transcript->tenant, function () use ($transcript, $transcriber): void {
            $transcript->forceFill(['status' => TranscriptStatus::Processing])->save();

            try {
                $recording = $transcript->recording()->firstOrFail();
                $result = $transcriber->transcribe($recording, $transcript->language);

                DB::transaction(function () use ($transcript, $result): void {
                    $transcript->segments()->delete();

                    foreach ($result->segments as $position => $segment) {
                        TranscriptSegment::query()->create([
                            'transcript_id' => $transcript->getKey(),
                            'position' => $position,
                            'start_ms' => $segment->startMs,
                            'end_ms' => $segment->endMs,
                            'speaker' => $segment->speaker,
                            'text' => $segment->text,
                        ]);
                    }

                    $transcript->forceFill([
                        'status' => TranscriptStatus::Ready,
                        'language' => $result->language,
                        'failed_reason' => null,
                    ])->save();

                    // Publish to the outbox (ADR-007) so the AI plane indexes it
                    // for semantic search / RAG (ADR-027).
                    OutboxEvent::query()->create([
                        'topic' => 'transcript.ready',
                        'payload' => ['transcript_id' => $transcript->getKey()],
                        'available_at' => now(),
                    ]);
                });
            } catch (Throwable $e) {
                $transcript->forceFill([
                    'status' => TranscriptStatus::Failed,
                    'failed_reason' => $e->getMessage(),
                ])->save();
                report($e);
            }
        });
    }
}
