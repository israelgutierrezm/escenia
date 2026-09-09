<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\EmbeddingProvider;
use App\Domain\Ai\Contracts\VectorIndex;
use App\Domain\Ai\Models\ContentChunk;
use App\Domain\Events\Models\Event;

/**
 * Semantic Replay: embeds a query and returns the closest content chunks for an
 * event, best match first. The vector index is swappable (DB by default, Qdrant
 * in production).
 */
final class SemanticSearch
{
    public function __construct(
        private readonly EmbeddingProvider $embeddings,
        private readonly VectorIndex $vectorIndex,
    ) {}

    /**
     * @return list<array{chunk: ContentChunk, score: float}>
     */
    public function search(Event $event, string $query, int $limit = 8): array
    {
        $vector = $this->embeddings->embed([$query])[0] ?? [];

        if ($vector === []) {
            return [];
        }

        $matches = $this->vectorIndex->search($event->tenant_id, $event->getKey(), $vector, $limit);

        if ($matches === []) {
            return [];
        }

        $chunks = ContentChunk::query()
            ->whereIn('id', array_column($matches, 'chunk_id'))
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($matches as $match) {
            $chunk = $chunks->get($match['chunk_id']);

            if ($chunk instanceof ContentChunk) {
                $results[] = ['chunk' => $chunk, 'score' => $match['score']];
            }
        }

        return $results;
    }
}
