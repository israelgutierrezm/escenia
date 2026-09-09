<?php

declare(strict_types=1);

namespace App\Domain\Ai\ValueObjects;

/**
 * A gateway-agnostic completion: the generated text and the model that produced
 * it. The LLM SDK never enters the domain.
 */
final class CompletionResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
    ) {}
}
