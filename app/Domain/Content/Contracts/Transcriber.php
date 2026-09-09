<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Models\Recording;
use App\Domain\Content\ValueObjects\TranscriptResult;

/**
 * Produces a transcript from a recording. Keeps the transcription SDK/API out of
 * the domain — callers get gateway-agnostic Value Objects. Runs inside a job
 * (heavy work off the request thread).
 */
interface Transcriber
{
    public function transcribe(Recording $recording, string $language): TranscriptResult;

    public function name(): string;
}
