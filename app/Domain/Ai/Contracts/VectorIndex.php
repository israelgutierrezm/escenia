<?php

declare(strict_types=1);

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Models\ContentChunk;

/**
 * Nearest-neighbour vector search over content chunks. The default searches the
 * `content_chunks` table directly (cosine in PHP); Qdrant is the production
 * adapter (ADR-027 / ADR-005). `index()` lets an external store ingest the
 * vector at write time (a no-op for the DB default, which reads the column).
 */
interface VectorIndex
{
    public function index(ContentChunk $chunk): void;

    /**
     * @param  list<float>  $vector
     * @return list<array{chunk_id: int, score: float}> best matches first
     */
    public function search(int $tenantId, ?int $eventId, array $vector, int $limit): array;
}
