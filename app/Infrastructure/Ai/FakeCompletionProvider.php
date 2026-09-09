<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Ai\ValueObjects\CompletionResult;
use Illuminate\Support\Str;

/**
 * Deterministic, network-free completion for dev and tests. It grounds its reply
 * in the prompt it is given (so RAG answers reflect the retrieved context and
 * summaries reflect the transcript) without calling any model.
 */
final class FakeCompletionProvider implements AiCompletionProvider
{
    public function complete(string $prompt, ?string $system = null): CompletionResult
    {
        $grounded = Str::limit(trim(preg_replace('/\s+/', ' ', $prompt) ?? $prompt), 480, '…');

        return new CompletionResult(
            text: "[fake-ai] {$grounded}",
            model: 'fake-1',
        );
    }

    public function name(): string
    {
        return 'fake';
    }
}
