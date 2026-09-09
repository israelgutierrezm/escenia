<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\VectorIndex;
use App\Domain\Ai\Models\ContentChunk;

/**
 * Default vector index: brute-force cosine similarity over the `content_chunks`
 * table. Simple and correct for MVP scale; the Qdrant adapter replaces it when
 * the corpus justifies it (ADR-005/027). `index()` is a no-op because the vector
 * already lives in the column.
 */
final class DatabaseVectorIndex implements VectorIndex
{
    public function index(ContentChunk $chunk): void
    {
        // No-op: the DB is the store; the vector is persisted on the chunk row.
    }

    /**
     * @param  list<float>  $vector
     * @return list<array{chunk_id: int, score: float}>
     */
    public function search(int $tenantId, ?int $eventId, array $vector, int $limit): array
    {
        $query = ContentChunk::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        $queryNorm = $this->norm($vector);

        if ($queryNorm === 0.0) {
            return [];
        }

        $scored = [];

        foreach ($query->get(['id', 'vector']) as $chunk) {
            /** @var list<float> $stored */
            $stored = array_map(static fn ($v): float => (float) $v, $chunk->vector);
            $score = $this->cosine($vector, $queryNorm, $stored);

            if ($score > 0.0) {
                $scored[] = ['chunk_id' => (int) $chunk->id, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param  list<float>  $query
     * @param  list<float>  $stored
     */
    private function cosine(array $query, float $queryNorm, array $stored): float
    {
        $storedNorm = $this->norm($stored);

        if ($storedNorm === 0.0) {
            return 0.0;
        }

        $dot = 0.0;
        $length = min(count($query), count($stored));

        for ($i = 0; $i < $length; $i++) {
            $dot += $query[$i] * $stored[$i];
        }

        return $dot / ($queryNorm * $storedNorm);
    }

    /**
     * @param  list<float>  $vector
     */
    private function norm(array $vector): float
    {
        return sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
    }
}
