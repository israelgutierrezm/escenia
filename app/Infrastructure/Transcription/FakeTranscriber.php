<?php

declare(strict_types=1);

namespace App\Infrastructure\Transcription;

use App\Domain\Content\Contracts\Transcriber;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\ValueObjects\TranscriptResult;
use App\Domain\Content\ValueObjects\TranscriptSegmentData;

/**
 * Deterministic, network-free transcriber for dev and tests. Returns a small,
 * fixed set of segments so the pipeline (job → segments → ready) is exercised
 * without a real transcription service.
 */
final class FakeTranscriber implements Transcriber
{
    public function transcribe(Recording $recording, string $language): TranscriptResult
    {
        return new TranscriptResult($language, [
            new TranscriptSegmentData(0, 4000, 'Host', 'Welcome to the session.'),
            new TranscriptSegmentData(4000, 9000, 'Host', 'Today we cover the roadmap.'),
            new TranscriptSegmentData(9000, 14000, 'Guest', 'Thanks for having me.'),
        ]);
    }

    public function name(): string
    {
        return 'fake';
    }
}
