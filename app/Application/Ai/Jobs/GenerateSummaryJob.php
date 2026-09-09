<?php

declare(strict_types=1);

namespace App\Application\Ai\Jobs;

use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Ai\Enums\SummaryKind;
use App\Domain\Ai\Enums\SummaryStatus;
use App\Domain\Ai\Models\ContentSummary;
use App\Domain\Content\Enums\TranscriptStatus;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Generates a recording's AI artifact off the request thread. Builds the prompt
 * from the recording's ready transcript, calls the completion provider, and
 * stores the result — marking the summary ready or failed. The transcript text
 * is data in the prompt, never instructions.
 */
class GenerateSummaryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $summaryId,
    ) {}

    public function handle(AiCompletionProvider $ai, TenantContext $tenantContext): void
    {
        $summary = ContentSummary::query()->withoutGlobalScopes()->find($this->summaryId);

        if ($summary === null || $summary->status === SummaryStatus::Ready) {
            return;
        }

        $tenantContext->runFor($summary->tenant, function () use ($summary, $ai): void {
            $summary->forceFill(['status' => SummaryStatus::Processing])->save();

            try {
                $transcript = $summary->recording()->firstOrFail()
                    ->transcripts()
                    ->where('status', TranscriptStatus::Ready->value)
                    ->latest('id')
                    ->first();

                if ($transcript === null) {
                    throw new RuntimeException('No ready transcript to summarize.');
                }

                $body = $transcript->segments()->get()
                    ->map(static fn ($s): string => "[{$s->start_ms}-{$s->end_ms}ms] {$s->text}")
                    ->implode("\n");

                $result = $ai->complete("{$this->instruction($summary->kind)}\n\nTranscript:\n{$body}");

                $summary->forceFill([
                    'status' => SummaryStatus::Ready,
                    'model' => $result->model,
                    'content' => $result->text,
                    'failed_reason' => null,
                ])->save();
            } catch (Throwable $e) {
                $summary->forceFill([
                    'status' => SummaryStatus::Failed,
                    'failed_reason' => $e->getMessage(),
                ])->save();
                report($e);
            }
        });
    }

    private function instruction(SummaryKind $kind): string
    {
        return match ($kind) {
            SummaryKind::Summary => 'Summarize this event transcript in a few clear paragraphs.',
            SummaryKind::Chapters => 'Break this transcript into chapters, each with a start timestamp and a short title.',
            SummaryKind::Highlights => 'List the key highlights and memorable moments from this transcript as bullet points.',
        };
    }
}
