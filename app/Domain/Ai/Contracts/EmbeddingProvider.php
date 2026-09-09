<?php

declare(strict_types=1);

namespace App\Domain\Ai\Contracts;

/**
 * Turns text into embedding vectors for semantic search / RAG. Keeps the
 * embeddings SDK/API out of the domain (ADR-027). The default is deterministic
 * and network-free.
 */
interface EmbeddingProvider
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per input, each of length dimensions()
     */
    public function embed(array $texts): array;

    public function dimensions(): int;

    public function name(): string;
}
