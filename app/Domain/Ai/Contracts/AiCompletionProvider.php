<?php

declare(strict_types=1);

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\ValueObjects\CompletionResult;

/**
 * Generates text completions (summaries, RAG answers, event plans). Keeps the
 * LLM SDK/API out of the domain (ADR-027). The default is deterministic and
 * network-free. Untrusted content passed in the user prompt is data, never
 * instructions.
 */
interface AiCompletionProvider
{
    public function complete(string $prompt, ?string $system = null): CompletionResult;

    public function name(): string;
}
